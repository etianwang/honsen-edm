<?php

namespace App\Console\Commands;

use App\Models\VersionDrawing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * 一次性维护命令：把已经上传到 COS 的历史 PDF 图纸对象元数据里的 Content-Disposition
 * 改成 inline（COS 对象默认是 attachment，导致预览弹窗里的 iframe 空白）。之后新上传的
 * PDF 已经在 CosFileService::store() 里正确设置好了，这个命令只补齐存量数据。
 *
 * COS 预签名 URL 不支持临时覆盖响应头，只能用 CopyObject（同名覆盖 + 元数据指令 Replaced）
 * 重写对象元数据本身，见 CosFileService::store() 的注释。
 */
class FixPdfInlineHeadersCommand extends Command
{
    protected $signature = 'app:fix-pdf-inline-headers {--dry-run : 只列出会处理哪些文件，不实际调用 COS}';

    protected $description = '把已上传到 COS 的历史 PDF 文件元数据改成 inline，修复预览空白问题';

    public function handle(): int
    {
        if (config('filesystems.default') !== 'cos') {
            $this->error('当前 FILESYSTEM_DISK 不是 cos，这个命令只用于修复 COS 上的历史文件，本地/测试环境不需要跑。');

            return self::FAILURE;
        }

        $drawings = VersionDrawing::where('kind', VersionDrawing::KIND_PDF)->whereNotNull('file_path')->get();

        if ($drawings->isEmpty()) {
            $this->info('没有找到任何 PDF 图纸记录，无需处理。');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[dry-run] ' : '')."找到 {$drawings->count()} 份 PDF，开始处理…");

        $diskConfig = config('filesystems.disks.cos');
        $objectClient = Storage::disk('cos')->getAdapter()->getObjectClient();

        $bar = $this->output->createProgressBar($drawings->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($drawings as $drawing) {
            $path = $drawing->file_path;

            if ($dryRun) {
                $bar->advance();

                continue;
            }

            try {
                $source = sprintf(
                    '%s-%s.cos.%s.myqcloud.com/%s',
                    $diskConfig['bucket'],
                    $diskConfig['app_id'],
                    $diskConfig['region'],
                    ltrim($path, '/')
                );

                $response = $objectClient->copyObject($path, [
                    'x-cos-copy-source' => $source,
                    'x-cos-metadata-directive' => 'Replaced',
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline',
                ]);

                if ($response->isSuccessful()) {
                    $success++;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->warn("失败：{$path} — HTTP {$response->getStatusCode()}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("失败：{$path} — {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info('dry-run 模式，没有实际修改任何文件。确认数量对得上后，去掉 --dry-run 参数正式执行：');
            $this->line('  php artisan app:fix-pdf-inline-headers');

            return self::SUCCESS;
        }

        $this->info("完成：成功 {$success} 份，失败 {$failed} 份。");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
