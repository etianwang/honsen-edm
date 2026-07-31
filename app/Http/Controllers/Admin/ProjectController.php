<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-taxonomy');

        $countries = Country::query()
            ->withCount('projects')
            ->with(['projects' => fn ($q) => $q->withCount('subcategories')->orderBy('id')])
            ->orderBy('id')
            ->get();

        return view('admin.projects', compact('countries'));
    }

    public function store(Request $request, Country $country): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $project = $country->projects()->create(['name' => $data['name']]);

        AuditLog::record(Auth::id(), 'create', 'project', $project->id, "在「{$country->name}」下添加项目「{$project->name}」");

        return back()->with('toast', '已添加项目');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $old = $project->name;
        $project->update(['name' => $data['name']]);

        AuditLog::record(Auth::id(), 'rename', 'project', $project->id, "项目「{$old}」重命名为「{$project->name}」");

        return back()->with('toast', '已重命名');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), Auth::user()->password)) {
            return back()->withErrors(['password' => '密码错误，请重试']);
        }

        $subCount = $project->subcategories()->count();
        $versionCount = \App\Models\Version::whereHas('subcategory', fn ($q) => $q->where('project_id', $project->id))->count();
        $project->delete();

        AuditLog::record(Auth::id(), 'delete', 'project', $project->id, "删除项目「{$project->name}」，其下 {$subCount} 个细分类、{$versionCount} 条变更记录一并不再可见");

        return back()->with('toast', '已删除项目');
    }
}
