<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 每个版本每个语言最多一份"变更说明文件"（doc/docx/xls/xlsx/pdf/txt/dwg 任一），可选、可替换。
 * DWG 原图和 PDF 图纸走 VersionDrawing，不在这里。
 */
#[Fillable(['version_id', 'language', 'doc_path', 'doc_size', 'original_name', 'uploaded_by'])]
class VersionFile extends Model
{
    const LANGUAGES = ['zh', 'fr', 'en'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
