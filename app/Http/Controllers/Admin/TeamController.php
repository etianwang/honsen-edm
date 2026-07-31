<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);

        $maxOrder = Team::max('sort_order') ?? 0;
        $team = Team::create(['name' => $data['name'], 'sort_order' => $maxOrder + 1]);

        AuditLog::record(Auth::id(), 'create', 'team', $team->id, "添加团队「{$team->name}」（全公司共享）");

        return back()->with('toast', '已添加团队（全公司共享，所有项目均可见）');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);
        $old = $team->name;
        $team->update(['name' => $data['name']]);

        AuditLog::record(Auth::id(), 'rename', 'team', $team->id, "团队「{$old}」重命名为「{$team->name}」");

        return back()->with('toast', '已重命名');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), Auth::user()->password)) {
            return back()->withErrors(['password' => '密码错误，请重试']);
        }

        [$specCount, $subCount, $versionCount, $projectCount] = DB::transaction(function () use ($team) {
            $specCount = $team->specialties()->count();
            $specialtyIds = $team->specialties()->pluck('id');
            $subCount = DB::table('subcategories')->whereIn('specialty_id', $specialtyIds)->whereNull('deleted_at')->count();
            $versionCount = DB::table('versions')
                ->join('subcategories', 'subcategories.id', '=', 'versions.subcategory_id')
                ->whereIn('subcategories.specialty_id', $specialtyIds)
                ->whereNull('versions.deleted_at')
                ->count();
            $projectCount = DB::table('subcategories')->whereIn('specialty_id', $specialtyIds)->whereNull('deleted_at')->distinct('project_id')->count('project_id');

            $team->specialties()->delete();
            $team->delete();

            return [$specCount, $subCount, $versionCount, $projectCount];
        });

        AuditLog::record(
            Auth::id(), 'delete', 'team', $team->id,
            "删除团队「{$team->name}」，含 {$specCount} 个专业、{$subCount} 个细分类、{$versionCount} 条变更记录，影响 {$projectCount} 个项目"
        );

        return back()->with('toast', '已删除团队');
    }
}
