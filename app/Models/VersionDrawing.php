<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一行 = 一份 DWG 原图或一份 PDF 图纸。一次变更同一语言下可以有多份（比如每层一张），
 * 可以随时追加、单独删除，互不影响。只有 DWG 才会走 DWG→DXF 异步转换（dxf_path/dxf_status）。
 */
#[Fillable(['version_id', 'language', 'kind', 'file_path', 'file_size', 'original_name', 'dxf_path', 'dxf_status', 'uploaded_by'])]
class VersionDrawing extends Model
{
    const KIND_DWG = 'dwg';

    const KIND_PDF = 'pdf';

    const DXF_PENDING = 'pending';

    const DXF_READY = 'ready';

    const DXF_FAILED = 'failed';

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isDwg(): bool
    {
        return $this->kind === self::KIND_DWG;
    }

    public function hasInteractivePreview(): bool
    {
        return ! empty($this->dxf_path);
    }

    public function isDxfConverting(): bool
    {
        return $this->dxf_status === self::DXF_PENDING;
    }
}
