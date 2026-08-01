<?php

namespace App\Jobs;

use App\Models\VersionDrawing;
use App\Services\CosFileService;
use App\Services\DwgConverter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * 上传 DWG 不再同步等转换（单个文件最多可能耗时 60 秒），改成后台异步跑；
 * 转换完之前，前端预览会显示"转换中"，用户可以先看到变更记录本身，图纸下载也不受影响。
 */
class ConvertVersionDrawingDxf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public function __construct(public int $versionDrawingId) {}

    public function handle(CosFileService $files, DwgConverter $converter): void
    {
        $drawing = VersionDrawing::find($this->versionDrawingId);
        if (! $drawing || ! $drawing->isDwg() || ! $drawing->file_path) {
            return;
        }

        $tmpDir = storage_path('app/tmp');
        @mkdir($tmpDir, 0755, true);
        $tmpDwg = $tmpDir.'/'.Str::uuid().'.dwg';
        file_put_contents($tmpDwg, $files->getContents($drawing->file_path));

        $dxfLocalPath = $converter->convertToDxf($tmpDwg);
        @unlink($tmpDwg);

        if (! $dxfLocalPath) {
            $drawing->update(['dxf_status' => VersionDrawing::DXF_FAILED]);

            return;
        }

        $result = $files->storeFromPath($dxfLocalPath, dirname($drawing->file_path), Str::uuid().'.dxf');
        @unlink($dxfLocalPath);

        $drawing->update([
            'dxf_path' => $result['path'],
            'dxf_status' => VersionDrawing::DXF_READY,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        VersionDrawing::whereKey($this->versionDrawingId)->update(['dxf_status' => VersionDrawing::DXF_FAILED]);
    }
}
