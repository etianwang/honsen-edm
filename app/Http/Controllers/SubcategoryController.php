<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Project;
use App\Models\Specialty;
use App\Models\Subcategory;
use App\Services\ProjectTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SubcategoryController extends Controller
{
    public function show(Project $project, Subcategory $subcategory): View
    {
        $this->authorize('view', $subcategory);

        abort_unless($subcategory->project_id === $project->id, 404);

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

        $subcategory->load(['specialty.team', 'versions.files', 'versions.drawings', 'versions.uploader']);

        return view('project.subcategory', [
            'countries' => $countries,
            'project' => $project,
            'tree' => $tree,
            'subcategory' => $subcategory,
        ]);
    }

    public function store(Request $request, Project $project, Specialty $specialty): RedirectResponse
    {
        $this->authorize('create', [Subcategory::class, $project, $specialty]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $subcategory = Subcategory::create([
            'project_id' => $project->id,
            'specialty_id' => $specialty->id,
            'name' => $data['name'],
            'created_by' => Auth::id(),
        ]);

        AuditLog::record(Auth::id(), 'create', 'subcategory', $subcategory->id, "创建细分类「{$subcategory->name}」");

        return redirect()->route('subcategory.show', [$project, $subcategory])->with('toast', '已添加细分类');
    }

    public function update(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $this->authorize('update', $subcategory);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $old = $subcategory->name;
        $subcategory->update(['name' => $data['name']]);

        AuditLog::record(Auth::id(), 'rename', 'subcategory', $subcategory->id, "细分类「{$old}」重命名为「{$subcategory->name}」");

        return back()->with('toast', '已重命名');
    }

    public function destroy(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $this->authorize('delete', $subcategory);

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), Auth::user()->password)) {
            return back()->withErrors(['password' => '密码错误，请重试']);
        }

        $versionCount = $subcategory->versions()->count();
        $project = $subcategory->project;

        $subcategory->delete();

        AuditLog::record(Auth::id(), 'delete', 'subcategory', $subcategory->id, "删除细分类「{$subcategory->name}」，影响 {$versionCount} 条变更记录");

        return redirect()->route('project.show', $project)->with('toast', '已删除细分类');
    }
}
