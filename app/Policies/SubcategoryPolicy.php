<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Specialty;
use App\Models\Subcategory;
use App\Models\User;

class SubcategoryPolicy
{
    public function view(User $user, Subcategory $subcategory): bool
    {
        return $user->hasProjectAccess($subcategory->project) && $user->hasTeamAccess($subcategory->specialty->team);
    }

    public function create(User $user, Project $project, Specialty $specialty): bool
    {
        return $user->canManageContent() && $user->hasProjectAccess($project) && $user->hasTeamAccess($specialty->team);
    }

    public function update(User $user, Subcategory $subcategory): bool
    {
        return $user->canManageContent() && $user->hasProjectAccess($subcategory->project) && $user->hasTeamAccess($subcategory->specialty->team);
    }

    public function delete(User $user, Subcategory $subcategory): bool
    {
        return $user->canManageContent() && $user->hasProjectAccess($subcategory->project) && $user->hasTeamAccess($subcategory->specialty->team);
    }
}
