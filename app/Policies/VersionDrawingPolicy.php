<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Version;
use App\Models\VersionDrawing;

class VersionDrawingPolicy
{
    public function create(User $user, Version $version): bool
    {
        return $user->canManageContent()
            && $user->hasProjectAccess($version->subcategory->project)
            && $user->hasTeamAccess($version->subcategory->specialty->team);
    }

    public function delete(User $user, VersionDrawing $drawing): bool
    {
        return $user->canManageContent()
            && $user->hasProjectAccess($drawing->version->subcategory->project)
            && $user->hasTeamAccess($drawing->version->subcategory->specialty->team);
    }
}
