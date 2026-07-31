<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * 用免费的 ODA File Converter 把 DWG 转成 DXF，供浏览器端的 dxf-viewer 交互式看图使用。
 * 没配置转换器路径、或转换失败时都直接返回 null，上层要能优雅降级为"只能下载，不能在线看图"。
 *
 * 下载与配置说明见 docs/DWG看图接入说明.md
 */
class DwgConverter
{
    public function isAvailable(): bool
    {
        $path = config('cad.oda_converter_path');
        if (empty($path) || ! is_file($path)) {
            return false;
        }

        if (config('cad.oda_use_xvfb') && ! $this->commandExists('xvfb-run')) {
            report(new \RuntimeException('ODA_USE_XVFB=true 但找不到 xvfb-run，请先 apt install xvfb'));

            return false;
        }

        return true;
    }

    private function commandExists(string $command): bool
    {
        $process = Process::fromShellCommandline('command -v '.escapeshellarg($command));
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @param  string  $dwgAbsolutePath  本地磁盘上真实存在的 .dwg 文件路径
     * @return string|null 转换成功后的 DXF 临时文件绝对路径（调用方用完需自行删除），失败返回 null
     */
    public function convertToDxf(string $dwgAbsolutePath): ?string
    {
        if (! $this->isAvailable() || ! is_file($dwgAbsolutePath)) {
            return null;
        }

        $workDir = storage_path('app/tmp/oda-'.Str::uuid());
        $inputDir = $workDir.'/in';
        $outputDir = $workDir.'/out';
        @mkdir($inputDir, 0755, true);
        @mkdir($outputDir, 0755, true);

        $inputFile = $inputDir.'/'.pathinfo($dwgAbsolutePath, PATHINFO_FILENAME).'.dwg';
        copy($dwgAbsolutePath, $inputFile);

        $command = [
            config('cad.oda_converter_path'),
            $inputDir,
            $outputDir,
            config('cad.oda_output_version', 'ACAD2013'),
            'DXF',
            '0', // 不递归子目录
            '1', // 转换时顺带修复文件
        ];

        // ODA File Converter 本质是个 Qt 图形界面程序，Linux 无显示器的服务器上要靠 xvfb-run
        // 起一个虚拟显示器才能跑，Windows 不需要这个
        if (config('cad.oda_use_xvfb')) {
            array_unshift($command, '-a');
            array_unshift($command, 'xvfb-run');
        }

        $process = new Process($command);
        $process->setTimeout((int) config('cad.oda_timeout', 60));

        try {
            $process->run();
        } catch (\Throwable $e) {
            report($e);
            $this->cleanup($workDir);

            return null;
        }

        if (! $process->isSuccessful()) {
            report(new ProcessFailedException($process));
            $this->cleanup($workDir);

            return null;
        }

        $dxfFiles = glob($outputDir.'/*.dxf');
        if (empty($dxfFiles)) {
            $this->cleanup($workDir);

            return null;
        }

        // 把结果挪到 workDir 外面（工作目录本身即将被清理），调用方负责后续删除
        $result = storage_path('app/tmp/'.Str::uuid().'.dxf');
        rename($dxfFiles[0], $result);
        $this->cleanup($workDir);

        return $result;
    }

    /**
     * 上传的 DWG 转换出 DXF 后直接存进当前文件磁盘，失败（含未配置转换器）时返回 null，
     * 调用方（Controller）不需要关心转换细节，只需要处理 null 的降级情况
     */
    public function convertAndStore(CosFileService $files, UploadedFile $dwg, string $directory): ?array
    {
        $localDxf = $this->convertToDxf($dwg->getRealPath());
        if (! $localDxf) {
            return null;
        }

        $result = $files->storeFromPath($localDxf, $directory, Str::uuid().'.dxf');
        @unlink($localDxf);

        return $result;
    }

    private function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
