<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-users');

        $users = User::query()->with(['projects', 'teams'])->orderBy('role')->orderBy('name')->get();
        $projects = Project::query()->with('country')->orderBy('id')->get();
        $teams = Team::query()->orderBy('sort_order')->get();

        return view('admin.users', compact('users', 'projects', 'teams'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        /** @var User $actor */
        $actor = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'login_id' => ['required', 'string', 'max:50', 'unique:users,login_id'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:'.implode(',', array_keys(User::ROLE_LABELS))],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ]);

        abort_unless($actor->canAssignRole($data['role']), 403, '只有超级管理员能创建管理员 / 超级管理员账号');

        $user = User::create([
            'name' => $data['name'],
            'login_id' => $data['login_id'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        $user->projects()->sync($data['project_ids'] ?? []);
        $user->teams()->sync($data['team_ids'] ?? []);

        AuditLog::record(Auth::id(), 'create', 'user', $user->id, "创建账号「{$user->name}」（{$user->login_id}），角色：".User::ROLE_LABELS[$user->role]);

        return back()->with('toast', '已创建账号');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        /** @var User $actor */
        $actor = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'role' => ['required', 'in:'.implode(',', array_keys(User::ROLE_LABELS))],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ]);

        // 目标角色和该用户当前角色都要在操作者的可分配范围内，防止普通管理员给别人提权、也防止降级/编辑已有的管理员账号
        abort_unless($actor->canAssignRole($data['role']) && $actor->canAssignRole($user->role), 403, '只有超级管理员能管理管理员 / 超级管理员账号');

        $user->name = $data['name'];
        $user->role = $data['role'];
        $user->is_active = $request->boolean('is_active', true);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->projects()->sync($data['project_ids'] ?? []);
        $user->teams()->sync($data['team_ids'] ?? []);

        AuditLog::record(Auth::id(), 'update', 'user', $user->id, "更新账号「{$user->name}」的角色 / 可见项目 / 可见团队");

        return back()->with('toast', '已保存账号信息');
    }
}
