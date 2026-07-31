<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Specialty;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class SpecialtyController extends Controller
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);

        $maxOrder = $team->specialties()->max('sort_order') ?? 0;
        $specialty = $team->specialties()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sort_order' => $maxOrder + 1,
        ]);

        AuditLog::record(Auth::id(), 'create', 'specialty', $specialty->id, "在团队「{$team->name}」下添加专业「{$specialty->name}」（全公司共享）");

        return back()->with('toast', '已添加专业（全公司共享，所有项目均可见）');
    }

    public function update(Request $request, Specialty $specialty): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);

        $old = $specialty->name;
        $specialty->update(['name' => $data['name'], 'code' => $data['code'] ?? $specialty->code]);

        AuditLog::record(Auth::id(), 'rename', 'specialty', $specialty->id, "专业「{$old}」重命名为「{$specialty->name}」");

        return back()->with('toast', '已重命名');
    }

    public function destroy(Request $request, Specialty $specialty): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), Auth::user()->password)) {
            return back()->withErrors(['password' => '密码错误，请重试']);
        }

        [$subCount, $versionCount, $projectCount] = DB::transaction(function () use ($specialty) {
            $subCount = $specialty->subcategories()->count();
            $versionCount = DB::table('versions')
                ->join('subcategories', 'subcategories.id', '=', 'versions.subcategory_id')
                ->where('subcategories.specialty_id', $specialty->id)
                ->whereNull('versions.deleted_at')
                ->count();
            $projectCount = $specialty->subcategories()->distinct('project_id')->count('project_id');

            $specialty->subcategories()->delete();
            $specialty->delete();

            return [$subCount, $versionCount, $projectCount];
        });

        AuditLog::record(
            Auth::id(), 'delete', 'specialty', $specialty->id,
            "删除专业「{$specialty->name}」，含 {$subCount} 个细分类、{$versionCount} 条变更记录，影响 {$projectCount} 个项目"
        );

        return back()->with('toast', '已删除专业');
    }
}
