<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['subcategory_id', 'version_no', 'description', 'publish_date', 'uploaded_by'])]
class Version extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'publish_date' => 'date',
        ];
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(VersionFile::class);
    }

    public function fileFor(string $language): ?VersionFile
    {
        return $this->files->firstWhere('language', $language);
    }
}
