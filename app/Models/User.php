<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'login_id', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    const ROLE_CONSTRUCTION = 'construction';

    const ROLE_DESIGNER = 'designer';

    const ROLE_ADMIN = 'admin';

    const ROLE_SUPER_ADMIN = 'super_admin';

    const ROLE_LABELS = [
        self::ROLE_CONSTRUCTION => '施工方',
        self::ROLE_DESIGNER => '设计师',
        self::ROLE_ADMIN => '管理员',
        self::ROLE_SUPER_ADMIN => '超级管理员',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * 管理员 / 超级管理员统称"管理层"，享有同一套内容与后台管理权限，
     * 区别只在于账号管理里能不能创建/编辑管理员级别的账号（见 canAssignRole）
     */
    public function isAdminTier(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    public function isDesigner(): bool
    {
        return $this->role === self::ROLE_DESIGNER;
    }

    /**
     * 管理层拥有设计师的全部内容管理权限（细分类 / 版本 / 语言文件），再加上团队专业管理和后台入口
     */
    public function canManageContent(): bool
    {
        return $this->isDesigner() || $this->isAdminTier();
    }

    /**
     * 管理层不受项目绑定限制，始终可见全部项目
     */
    public function canSeeAllProjects(): bool
    {
        return $this->isAdminTier();
    }

    /**
     * 管理层不受团队绑定限制，始终可见全部团队（土建/机电/精装）
     */
    public function canSeeAllTeams(): bool
    {
        return $this->isAdminTier();
    }

    /**
     * 只有超级管理员能创建/编辑管理员或超级管理员账号，避免普通管理员随意提权
     */
    public function canAssignRole(string $role): bool
    {
        if (in_array($role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true)) {
            return $this->isSuperAdmin();
        }

        return $this->isAdminTier();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'user_project');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'user_team');
    }

    public function hasProjectAccess(Project $project): bool
    {
        return $this->canSeeAllProjects() || $this->projects()->whereKey($project->id)->exists();
    }

    public function hasTeamAccess(Team $team): bool
    {
        return $this->canSeeAllTeams() || $this->teams()->whereKey($team->id)->exists();
    }
}
