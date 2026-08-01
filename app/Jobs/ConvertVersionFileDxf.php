<?php

namespace App\Jobs;

use App\Models\VersionFile;
use App\Services\CosFileService;
use App\Services\DwgConverter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * 上传变更时不再同步等 DWG→DXF 转换（单个文件最多可能耗时 60 秒），改成后台异步跑；
 * 转换完之前，前端预览会显示"转换中"，用户可以先看到变更记录本身，图纸下载也不受影响。
 */
class ConvertVersionFileDxf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public function __construct(public int $versionFileId) {}

    public function handle(CosFileService $files, DwgConverter $converter): void
    {
        $versionFile = VersionFile::find($this->versionFileId);
        if (! $versionFile || ! $versionFile->dwg_path) {
            return;
        }

        $tmpDir = storage_path('app/tmp');
        @mkdir($tmpDir, 0755, true);
        $tmpDwg = $tmpDir.'/'.Str::uuid().'.dwg';
        file_put_contents($tmpDwg, $files->getContents($versionFile->dwg_path));

        $dxfLocalPath = $converter->convertToDxf($tmpDwg);
        @unlink($tmpDwg);

        if (! $dxfLocalPath) {
            $versionFile->update(['dxf_status' => VersionFile::DXF_FAILED]);

            return;
        }

        $result = $files->storeFromPath($dxfLocalPath, dirname($versionFile->dwg_path), Str::uuid().'.dxf');
        @unlink($dxfLocalPath);

        $versionFile->update([
            'dxf_path' => $result['path'],
            'dxf_status' => VersionFile::DXF_READY,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        VersionFile::whereKey($this->versionFileId)->update(['dxf_status' => VersionFile::DXF_FAILED]);
    }
}
