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
        // 说明文件现在所有语言都是可选的，DWG 的"中文至少留一份"规则在 VersionDrawing 那边处理
        return $user->canManageContent()
            && $user->hasProjectAccess($versionFile->version->subcategory->project)
            && $user->hasTeamAccess($versionFile->version->subcategory->specialty->team);
    }
}
