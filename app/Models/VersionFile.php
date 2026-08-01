<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['version_id', 'language', 'dwg_path', 'dwg_size', 'dxf_path', 'dxf_status', 'doc_path', 'doc_size', 'uploaded_by'])]
class VersionFile extends Model
{
    const LANGUAGES = ['zh', 'fr', 'en'];

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

    public function hasInteractivePreview(): bool
    {
        return ! empty($this->dxf_path);
    }

    public function isDxfConverting(): bool
    {
        return $this->dxf_status === self::DXF_PENDING;
    }
}
