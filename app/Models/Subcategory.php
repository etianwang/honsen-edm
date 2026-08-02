<?php

namespace App\Models;

use App\Models\Concerns\GuardsAgainstForceDelete;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['project_id', 'specialty_id', 'name', 'code', 'created_by'])]
class Subcategory extends Model
{
    use SoftDeletes, GuardsAgainstForceDelete {
        GuardsAgainstForceDelete::forceDelete insteadof SoftDeletes;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class)->orderByDesc('publish_date')->orderByDesc('id');
    }

    public function latestVersion(): ?Version
    {
        return $this->versions->first();
    }
}
