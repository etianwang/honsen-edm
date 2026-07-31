<?php

namespace App\Policies;

use App\Models\Subcategory;
use App\Models\User;
use App\Models\Version;

class VersionPolicy
{
    public function view(User $user, Version $version): bool
    {
        return $user->hasProjectAccess($version->subcategory->project)
            && $user->hasTeamAccess($version->subcategory->specialty->team);
    }

    public function create(User $user, Subcategory $subcategory): bool
    {
        return $user->canManageContent()
            && $user->hasProjectAccess($subcategory->project)
            && $user->hasTeamAccess($subcategory->specialty->team);
    }

    public function delete(User $user, Version $version): bool
    {
        return $user->canManageContent()
            && $user->hasProjectAccess($version->subcategory->project)
            && $user->hasTeamAccess($version->subcategory->specialty->team);
    }
}
