<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 构建某个项目下的 团队 → 专业 → 细分类 导航树。
 * 团队/专业为全公司共享的标准分类，细分类是当前项目自己的数据。
 * 非管理层账号还会按 user_team 绑定过滤团队（土建/机电/精装互相隔离）。
 */
class ProjectTree
{
    public static function build(Project $project, ?User $user = null): Collection
    {
        return Team::query()
            ->when($user && ! $user->canSeeAllTeams(), function ($query) use ($user) {
                $query->whereHas('users', fn ($q) => $q->whereKey($user->id));
            })
            ->orderBy('sort_order')
            ->with(['specialties' => function ($query) use ($project) {
                $query->orderBy('sort_order')
                    ->with(['subcategories' => function ($subQuery) use ($project) {
                        $subQuery->where('project_id', $project->id)
                            ->withCount('versions')
                            ->orderBy('name');
                    }]);
            }])
            ->get();
    }
}
