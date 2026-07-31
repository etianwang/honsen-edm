<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Version;
use App\Models\VersionFile;

class VersionFilePolicy
{
    public function create(User $user, Version $version): bool
    {
        return $user->canManageContent()
            && $user->hasProjectAccess($version->subcategory->project)
            && $user->hasTeamAccess($version->subcategory->specialty->team);
    }

    public function update(User $user, VersionFile $versionFile): bool
    {
        return $user->canManageContent()
            && $user->hasProjectAccess($versionFile->version->subcategory->project)
            && $user->hasTeamAccess($versionFile->version->subcategory->specialty->team);
    }

    public function delete(User $user, VersionFile $versionFile): bool
    {
        if ($versionFile->language === 'zh') {
            return false; // 中文版本为必填，不允许移除
        }

        return $user->canManageContent()
            && $user->hasProjectAccess($versionFile->version->subcategory->project)
            && $user->hasTeamAccess($versionFile->version->subcategory->specialty->team);
    }
}
