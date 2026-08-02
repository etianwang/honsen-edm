<?php

namespace App\Models;

use App\Models\Concerns\GuardsAgainstForceDelete;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sort_order'])]
class Team extends Model
{
    use SoftDeletes, GuardsAgainstForceDelete {
        GuardsAgainstForceDelete::forceDelete insteadof SoftDeletes;
    }

    public function specialties(): HasMany
    {
        return $this->hasMany(Specialty::class)->orderBy('sort_order');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_team');
    }
}
