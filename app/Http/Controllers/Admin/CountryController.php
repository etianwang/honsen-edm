<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class CountryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);

        $country = Country::create(['name' => $data['name']]);

        AuditLog::record(Auth::id(), 'create', 'country', $country->id, "添加国家「{$country->name}」");

        return back()->with('toast', '已添加国家');
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);
        $old = $country->name;
        $country->update(['name' => $data['name']]);

        AuditLog::record(Auth::id(), 'rename', 'country', $country->id, "国家「{$old}」重命名为「{$country->name}」");

        return back()->with('toast', '已重命名');
    }

    public function destroy(Request $request, Country $country): RedirectResponse
    {
        Gate::authorize('manage-taxonomy');

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), Auth::user()->password)) {
            return back()->withErrors(['password' => '密码错误，请重试']);
        }

        $projectCount = $country->projects()->count();
        $country->delete();

        AuditLog::record(Auth::id(), 'delete', 'country', $country->id, "删除国家「{$country->name}」，其下 {$projectCount} 个项目一并不再可见");

        return back()->with('toast', '已删除国家');
    }
}
