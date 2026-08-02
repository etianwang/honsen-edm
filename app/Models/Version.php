<?php

namespace App\Models;

use App\Models\Concerns\GuardsAgainstForceDelete;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['subcategory_id', 'version_no', 'description', 'publish_date', 'uploaded_by'])]
class Version extends Model
{
    use SoftDeletes, GuardsAgainstForceDelete {
        GuardsAgainstForceDelete::forceDelete insteadof SoftDeletes;
    }

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

    public function drawings(): HasMany
    {
        return $this->hasMany(VersionDrawing::class);
    }

    public function fileFor(string $language): ?VersionFile
    {
        return $this->files->firstWhere('language', $language);
    }

    /**
     * @return \Illuminate\Support\Collection<int, VersionDrawing>
     */
    public function drawingsFor(string $language, ?string $kind = null): \Illuminate\Support\Collection
    {
        return $this->drawings
            ->where('language', $language)
            ->when($kind, fn ($drawings) => $drawings->where('kind', $kind))
            ->values();
    }

    /**
     * 一个语言只要有任意一份 DWG / PDF / 说明文件，就算这个语言"存在"
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function availableLanguages(): \Illuminate\Support\Collection
    {
        return $this->drawings->pluck('language')
            ->merge($this->files->whereNotNull('doc_path')->pluck('language'))
            ->unique()
            ->values();
    }
}
