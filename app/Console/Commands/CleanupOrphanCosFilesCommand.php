<?php

namespace App\Console\Commands;

use App\Models\VersionDrawing;
use App\Models\VersionFile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * 清理磁盘（生产环境是 COS）上"数据库里已经没有任何记录引用，但对象还留着"的孤儿文件。
 *
 * 孤儿文件的主要来源：
 * 1. CosFileService::delete() 是"尽力删除 + 记日志"（见 debug_memory.md Task #39），COS
 *    侧删除失败时不会阻塞数据库那边记录的删除，失败的那次删除就会留下孤儿。
 * 2. "发布变更"一次提交里往往有好几个文件，前面几个已经真的 PUT 到 COS 成功、后面某个
 *    文件失败（比如 COS 侧的 451/403）导致整个 DB::transaction() 回滚——前面那几个 COS
 *    对象从来没有对应的数据库记录，也是孤儿。
 *
 * 默认 dry-run（只列出会删哪些、不实际删除），必须显式加 --force 才会真的调用
 * Storage::delete()。只扫描 projects/ 前缀下的对象（这是应用自己存文件用的路径体系），
 * 不会碰这个前缀之外的任何东西。默认跳过最近 24 小时内修改过的对象（--min-age-hours
 * 可调），避免误删正在进行中、所在的数据库事务还没来得及提交完的上传。
 */
class CleanupOrphanCosFilesCommand extends Command
{
    protected $signature = 'cos:cleanup-orphans
        {--force : 真的删除孤儿文件；不加这个参数只会列出来，不会实际删除}
        {--disk= : 要清理的磁盘，默认用 filesystems.default}
        {--min-age-hours=24 : 只处理修改时间早于这么多小时之前的对象，避免误删正在进行中的上传}';

    protected $description = '扫描存储磁盘，列出/删除数据库记录里已经没有引用的孤儿文件（默认 dry-run）';

    public function handle(): int
    {
        $disk = $this->option('disk') ?: config('filesystems.default');
        $force = (bool) $this->option('force');
        $minAgeHours = (int) $this->option('min-age-hours');

        $this->info(($force ? '[执行]' : '[dry-run]')." 磁盘：{$disk}，最小保留时长：{$minAgeHours} 小时");

        $referenced = $this->referencedPaths();
        $this->line("数据库里引用的文件路径共 {$referenced->count()} 个。");

        $allObjects = collect(Storage::disk($disk)->allFiles('projects'));
        $this->line("磁盘上 projects/ 前缀下共 {$allObjects->count()} 个对象。");

        $referencedSet = array_flip($referenced->all());
        $candidates = $allObjects->reject(fn ($path) => isset($referencedSet[$path]));

        $cutoff = now()->subHours($minAgeHours)->timestamp;
        $tooRecent = 0;
        $orphans = collect();

        foreach ($candidates as $path) {
            $modifiedAt = Storage::disk($disk)->lastModified($path);
            if ($modifiedAt !== false && $modifiedAt > $cutoff) {
                $tooRecent++;

                continue;
            }
            $orphans->push($path);
        }

        if ($tooRecent > 0) {
            $this->line("另有 {$tooRecent} 个候选对象修改时间在 {$minAgeHours} 小时以内，为避免误删正在进行的上传，本次跳过。");
        }

        if ($orphans->isEmpty()) {
            $this->info('没有找到需要清理的孤儿文件。');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("找到 {$orphans->count()} 个孤儿文件：");
        foreach ($orphans as $path) {
            $size = Storage::disk($disk)->size($path);
            $this->line('  '.$path.' ('.$this->formatBytes($size).')');
        }

        if (! $force) {
            $this->newLine();
            $this->info('dry-run 模式，没有实际删除任何文件。确认列表没问题后，加 --force 正式执行：');
            $this->line('  php artisan cos:cleanup-orphans --force');

            return self::SUCCESS;
        }

        $this->newLine();
        $deleted = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($orphans->count());
        $bar->start();

        foreach ($orphans as $path) {
            try {
                Storage::disk($disk)->delete($path);
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("删除失败：{$path} — {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("完成：删除 {$deleted} 个，失败 {$failed} 个。");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function referencedPaths(): Collection
    {
        return collect()
            ->merge(VersionDrawing::whereNotNull('file_path')->pluck('file_path'))
            ->merge(VersionDrawing::whereNotNull('dxf_path')->pluck('dxf_path'))
            ->merge(VersionFile::whereNotNull('doc_path')->pluck('doc_path'))
            ->filter()
            ->unique()
            ->values();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
