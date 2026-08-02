<?php

namespace App\Models;

use App\Models\Concerns\GuardsAgainstForceDelete;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name'])]
class Country extends Model
{
    use SoftDeletes, GuardsAgainstForceDelete {
        GuardsAgainstForceDelete::forceDelete insteadof SoftDeletes;
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
