<?php

namespace App\Models;

use App\Models\Concerns\GuardsAgainstForceDelete;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['team_id', 'name', 'code', 'sort_order'])]
class Specialty extends Model
{
    use SoftDeletes, GuardsAgainstForceDelete {
        GuardsAgainstForceDelete::forceDelete insteadof SoftDeletes;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }
}
