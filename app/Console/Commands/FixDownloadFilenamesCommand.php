<?php

namespace App\Console\Commands;

use App\Models\VersionDrawing;
use App\Services\CosFileService;
use Illuminate\Console\Command;

/**
 * 一次性维护命令：修复已上传到 COS 的历史图纸文件下载时文件名显示成 UUID 的问题。
 * 之后新上传的文件已经在 CosFileService::store() 里正确写好了 Content-Disposition
 * 元数据（含原始文件名），这个命令只补齐存量数据。
 *
 * 说明文件（VersionFile）的原始文件名字段是这次一起加的，历史记录里本来就没存过
 * 原始文件名，没法补——这批只能等用户重新上传/替换一次才会有正确文件名，命令里
 * 会统计但不处理这部分。
 */
class FixDownloadFilenamesCommand extends Command
{
    protected $signature = 'app:fix-download-filenames {--dry-run : 只列出会处理哪些文件，不实际调用 COS}';

    protected $description = '修复已上传到 COS 的历史图纸文件下载时文件名显示成 UUID 的问题';

    public function handle(CosFileService $files): int
    {
        if (config('filesystems.default') !== 'cos') {
            $this->error('当前 FILESYSTEM_DISK 不是 cos，这个命令只用于修复 COS 上的历史文件，本地/测试环境不需要跑。');

            return self::FAILURE;
        }

        $drawings = VersionDrawing::whereNotNull('file_path')->whereNotNull('original_name')->get();

        if ($drawings->isEmpty()) {
            $this->info('没有找到任何图纸记录，无需处理。');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[dry-run] ' : '')."找到 {$drawings->count()} 份图纸文件，开始处理…");

        $bar = $this->output->createProgressBar($drawings->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($drawings as $drawing) {
            if ($dryRun) {
                $bar->advance();

                continue;
            }

            try {
                $ok = $files->refreshContentDisposition(
                    $drawing->file_path,
                    $drawing->original_name,
                    inline: $drawing->kind === VersionDrawing::KIND_PDF
                );

                if ($ok) {
                    $success++;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->warn("失败：{$drawing->file_path}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("失败：{$drawing->file_path} — {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info('dry-run 模式，没有实际修改任何文件。确认数量对得上后，去掉 --dry-run 参数正式执行：');
            $this->line('  php artisan app:fix-download-filenames');

            return self::SUCCESS;
        }

        $this->info("完成：成功 {$success} 份，失败 {$failed} 份。");
        $this->comment('说明文件（变更说明文档）历史记录没有存过原始文件名，没法用这个命令补，需要重新上传/替换一次才会显示正确文件名。');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
