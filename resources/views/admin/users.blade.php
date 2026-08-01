@extends('layouts.app')
@section('title', '账号管理')
@section('content')
@php
  $roleLabel = \App\Models\User::ROLE_LABELS;
  $assignableRoles = collect($roleLabel)->filter(fn ($label, $value) => $user->canAssignRole($value));
@endphp
<div class="admin-shell">
  <div class="admin-inner">
    <a href="{{ route('admin.index') }}" style="font-size:12.5px;color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:14px;">← 返回后台首页</a>
    <div class="admin-head-row">
      <div>
        <h1 class="page-title">账号管理</h1>
        <p class="page-sub" style="margin:0;">管理员 / 超级管理员不受项目和团队绑定限制，始终可见全部；设计师 / 施工方仅能看到被勾选的项目和团队。只有超级管理员能创建或编辑管理员级别的账号。</p>
      </div>
      <button type="button" class="btn btn-accent" onclick="document.getElementById('add-user-modal').style.display='flex'">+ 创建账号</button>
    </div>

    @if(session('toast'))<div class="admin-note" style="background:var(--success-bg);color:var(--success);">{{ session('toast') }}</div>@endif
    @if($errors->any())<div class="admin-note" style="background:var(--danger-bg);color:var(--danger);">{{ $errors->first() }}</div>@endif

    <table class="revlog">
      <thead><tr><th>姓名</th><th>登录标识</th><th>角色</th><th>可见项目</th><th>可见团队</th><th style="width:70px;">状态</th><th style="width:70px;">操作</th></tr></thead>
      <tbody>
        @foreach($users as $u)
          <tr>
            <td>{{ $u->name }}</td>
            <td class="mono">{{ $u->login_id }}</td>
            <td>{{ $roleLabel[$u->role] }}</td>
            <td class="desc-cell">{{ $u->canSeeAllProjects() ? '全部项目' : ($u->projects->pluck('name')->join('、') ?: '未分配') }}</td>
            <td class="desc-cell">{{ $u->canSeeAllTeams() ? '全部团队' : ($u->teams->pluck('name')->join('、') ?: '未分配') }}</td>
            <td>{{ $u->is_active ? '正常' : '已禁用' }}</td>
            <td>
              @if($user->canAssignRole($u->role))
                <button type="button" class="act-btn" title="编辑" onclick="document.getElementById('edit-user-{{ $u->id }}').style.display='flex'">✎</button>
              @else
                <span class="hint">仅超级管理员可编辑</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    @foreach($users as $u)
      @continue(! $user->canAssignRole($u->role))
      <div id="edit-user-{{ $u->id }}" class="overlay" style="display:none;">
        <div class="modal">
          <div class="modal-head"><h3>编辑账号「{{ $u->name }}」</h3><button type="button" class="modal-close" onclick="document.getElementById('edit-user-{{ $u->id }}').style.display='none'">&times;</button></div>
          <form method="POST" action="{{ route('admin.users.update', $u) }}">
            @csrf @method('PATCH')
            <div class="modal-body">
              <div class="field"><label>姓名</label><input type="text" name="name" value="{{ $u->name }}" required></div>
              <div class="field">
                <label>角色</label>
                <select name="role">
                  @foreach($assignableRoles as $value => $label)
                    <option value="{{ $value }}" {{ $u->role === $value ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field"><label>新密码（留空则不修改）</label><input type="password" name="password" autocomplete="off"></div>
              <div class="field">
                <label><input type="checkbox" name="is_active" value="1" {{ $u->is_active ? 'checked' : '' }} style="width:auto;"> 账号启用</label>
              </div>
              <div class="field">
                <label>可见项目（管理层忽略此项，始终可见全部）</label>
                <div class="sub-chip-list">
                  @foreach($projects as $p)
                    <label class="sub-chip" style="cursor:pointer;">
                      <input type="checkbox" name="project_ids[]" value="{{ $p->id }}" style="width:auto;" {{ $u->projects->contains('id', $p->id) ? 'checked' : '' }}>
                      {{ $p->country->name }} · {{ $p->name }}
                    </label>
                  @endforeach
                </div>
              </div>
              <div class="field">
                <label>可见团队（管理层忽略此项，始终可见全部）</label>
                <div class="sub-chip-list">
                  @foreach($teams as $t)
                    <label class="sub-chip" style="cursor:pointer;">
                      <input type="checkbox" name="team_ids[]" value="{{ $t->id }}" style="width:auto;" {{ $u->teams->contains('id', $t->id) ? 'checked' : '' }}>
                      {{ $t->name }}
                    </label>
                  @endforeach
                </div>
              </div>
            </div>
            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-user-{{ $u->id }}').style.display='none'">取消</button>
              <button type="submit" class="btn btn-accent">保存</button>
            </div>
          </form>
        </div>
      </div>
    @endforeach

    <div id="add-user-modal" class="overlay" style="display:none;">
      <div class="modal">
        <div class="modal-head"><h3>创建账号</h3><button type="button" class="modal-close" onclick="document.getElementById('add-user-modal').style.display='none'">&times;</button></div>
        <form method="POST" action="{{ route('admin.users.store') }}">
          @csrf
          <div class="modal-body">
            <div class="field"><label>姓名</label><input type="text" name="name" required autofocus></div>
            <div class="field"><label>登录标识（工号 / 手机号）</label><input type="text" name="login_id" required></div>
            <div class="field"><label>初始密码</label><input type="password" name="password" required minlength="8"></div>
            <div class="field">
              <label>角色</label>
              <select name="role">
                @foreach($assignableRoles as $value => $label)
                  <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="field">
              <label>可见项目</label>
              <div class="sub-chip-list">
                @foreach($projects as $p)
                  <label class="sub-chip" style="cursor:pointer;">
                    <input type="checkbox" name="project_ids[]" value="{{ $p->id }}" style="width:auto;">
                    {{ $p->country->name }} · {{ $p->name }}
                  </label>
                @endforeach
              </div>
            </div>
            <div class="field">
              <label>可见团队</label>
              <div class="sub-chip-list">
                @foreach($teams as $t)
                  <label class="sub-chip" style="cursor:pointer;">
                    <input type="checkbox" name="team_ids[]" value="{{ $t->id }}" style="width:auto;">
                    {{ $t->name }}
                  </label>
                @endforeach
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-user-modal').style.display='none'">取消</button>
            <button type="submit" class="btn btn-accent">创建</button>
          </div>
        </form>
      </div>
    </div>

    @include('partials.footer')
  </div>
</div>
@endsection
