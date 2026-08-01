<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Project;
use App\Models\Specialty;
use App\Models\Subcategory;
use App\Models\Team;
use App\Models\User;
use App\Models\Version;
use App\Services\ProjectTree;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function show(Project $project): View
    {
        /** @var User $user */
        $user = Auth::user();

        // 非管理层账号只能看到被分配团队下的数据，统计和最新变更流也要按团队过滤
        $teamIds = $user->canSeeAllTeams() ? null : $user->teams()->pluck('teams.id')->all();

        [$stats, $feed] = $this->buildStatsAndFeed($project, $teamIds, null);

        return view('project.overview', [
            'countries' => $this->accessibleCountries($user),
            'project' => $project,
            'tree' => ProjectTree::build($project, $user),
            'stats' => $stats,
            'feed' => $feed,
        ]);
    }

    public function showTeam(Project $project, Team $team): View
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->hasTeamAccess($team), 403, '你没有权限查看该团队');

        $tree = ProjectTree::build($project, $user);
        $teamNode = $tree->firstWhere('id', $team->id);
        abort_unless($teamNode, 404);

        [$stats, $feed] = $this->buildStatsAndFeed($project, [$team->id], null);

        return view('project.team', [
            'countries' => $this->accessibleCountries($user),
            'project' => $project,
            'tree' => $tree,
            'team' => $teamNode,
            'stats' => $stats,
            'feed' => $feed,
        ]);
    }

    public function showSpecialty(Project $project, Specialty $specialty): View
    {
        /** @var User $user */
        $user = Auth::user();
        $specialty->loadMissing('team');
        abort_unless($user->hasTeamAccess($specialty->team), 403, '你没有权限查看该专业');

        $tree = ProjectTree::build($project, $user);
        $specialtyNode = $tree->flatMap(fn ($team) => $team->specialties)->firstWhere('id', $specialty->id);
        abort_unless($specialtyNode, 404);

        [$stats, $feed] = $this->buildStatsAndFeed($project, null, $specialty->id);

        return view('project.specialty', [
            'countries' => $this->accessibleCountries($user),
            'project' => $project,
            'tree' => $tree,
            'specialty' => $specialty,
            'specialtyNode' => $specialtyNode,
            'stats' => $stats,
            'feed' => $feed,
        ]);
    }

    private function accessibleCountries(User $user): Collection
    {
        return Country::query()
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
    }

    /**
     * @param  int[]|null  $teamIds  非空数组时按这些团队过滤；null 表示不按团队过滤
     * @param  int|null  $specialtyId  非空时按这个专业过滤，优先级高于 $teamIds
     */
    private function buildStatsAndFeed(Project $project, ?array $teamIds, ?int $specialtyId): array
    {
        $scopeSub = function ($query) use ($teamIds, $specialtyId) {
            if ($specialtyId !== null) {
                return $query->where('specialty_id', $specialtyId);
            }
            if ($teamIds !== null) {
                return $query->whereHas('specialty', fn ($q) => $q->whereIn('team_id', $teamIds));
            }

            return $query;
        };

        $versionsQuery = function () use ($project, $scopeSub) {
            $query = Version::query()->whereHas('subcategory', function ($q) use ($project, $scopeSub) {
                $q->where('project_id', $project->id);
                $scopeSub($q);
            });

            return $query;
        };

        $stats = [
            'subcategories' => $scopeSub(Subcategory::query()->where('project_id', $project->id))->count(),
            'versions' => $versionsQuery()->count(),
            'this_month' => $versionsQuery()
                ->whereYear('publish_date', now()->year)
                ->whereMonth('publish_date', now()->month)
                ->count(),
            'external' => $versionsQuery()
                ->where(function ($q) {
                    $q->whereHas('files', fn ($q2) => $q2->whereIn('language', ['fr', 'en'])->whereNotNull('doc_path'))
                        ->orWhereHas('drawings', fn ($q2) => $q2->whereIn('language', ['fr', 'en']));
                })
                ->count(),
        ];

        $feed = $versionsQuery()
            ->with(['subcategory.specialty.team', 'files', 'drawings'])
            ->orderByDesc('publish_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [$stats, $feed];
    }
}
