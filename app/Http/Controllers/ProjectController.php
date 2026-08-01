<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Project;
use App\Models\Version;
use App\Services\ProjectTree;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function show(Project $project): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $countries = Country::query()
            ->with(['projects' => function ($query) use ($user) {
                if (! $user->canSeeAllProjects()) {
                    $query->whereHas('users', fn ($q) => $q->whereKey($user->id));
                }
                $query->orderBy('id');
            }])
            ->orderBy('id')
            ->get()
            ->filter(fn (Country $country) => $country->projects->isNotEmpty())
            ->values();

        $tree = ProjectTree::build($project, $user);

        // 非管理层账号只能看到被分配团队下的数据，统计和最新变更流也要按团队过滤
        $teamIds = $user->canSeeAllTeams() ? null : $user->teams()->pluck('teams.id');
        $scopeByTeam = fn ($query) => $teamIds !== null
            ? $query->whereHas('subcategory.specialty', fn ($q) => $q->whereIn('team_id', $teamIds))
            : $query;

        $stats = [
            'subcategories' => $tree->flatMap(fn ($team) => $team->specialties)->flatMap(fn ($spec) => $spec->subcategories)->count(),
            'versions' => $scopeByTeam(Version::query()->whereHas('subcategory', fn ($q) => $q->where('project_id', $project->id)))->count(),
            'this_month' => $scopeByTeam(Version::query()->whereHas('subcategory', fn ($q) => $q->where('project_id', $project->id)))
                ->whereYear('publish_date', now()->year)
                ->whereMonth('publish_date', now()->month)
                ->count(),
            'external' => $scopeByTeam(Version::query()->whereHas('subcategory', fn ($q) => $q->where('project_id', $project->id)))
                ->where(function ($q) {
                    $q->whereHas('files', fn ($q2) => $q2->whereIn('language', ['fr', 'en'])->whereNotNull('doc_path'))
                        ->orWhereHas('drawings', fn ($q2) => $q2->whereIn('language', ['fr', 'en']));
                })
                ->count(),
        ];

        $feed = $scopeByTeam(
            Version::query()->whereHas('subcategory', fn ($q) => $q->where('project_id', $project->id))
        )
            ->with(['subcategory.specialty.team', 'files', 'drawings'])
            ->orderByDesc('publish_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('project.overview', [
            'countries' => $countries,
            'project' => $project,
            'tree' => $tree,
            'stats' => $stats,
            'feed' => $feed,
        ]);
    }
}
