@extends('layouts.app')
@section('title', '后台管理')
@section('content')
<div class="admin-shell">
  <div class="admin-inner">
    <a href="{{ route('home') }}" style="font-size:12.5px;color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:14px;">← 返回前台</a>
    <div class="admin-head-row">
      <div>
        <h1 class="page-title">后台管理</h1>
        <p class="page-sub" style="margin:0;">团队 / 专业为全公司统一标准</p>
      </div>
      <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.users.index') }}" class="btn">账号管理</a>
        <button type="button" class="btn btn-accent" onclick="document.getElementById('add-team-modal').style.display='flex'">+ 添加团队</button>
      </div>
    </div>
    <div class="admin-note">团队与专业是全公司统一的标准分类，在这里增删改会同步影响所有国家和项目；细分类则按项目单独管理，不会影响其他项目。设计师只能管理细分类，施工方仅可查看和下载。删除操作均需要输入密码确认。</div>

    @if(session('toast'))<div class="admin-note" style="background:var(--success-bg);color:var(--success);">{{ session('toast') }}</div>@endif
    @if($errors->any())<div class="admin-note" style="background:var(--danger-bg);color:var(--danger);">{{ $errors->first() }}</div>@endif

    @foreach($teams as $team)
      <div class="team-card">
        <div class="team-card-head">
          <span class="tc-name">{{ $team->name }}</span>
          <span class="badge-count">{{ $team->specialties->count() }} 个专业</span>
          <button type="button" class="act-btn" title="重命名团队" onclick="document.getElementById('rename-team-{{ $team->id }}').style.display='flex'">✎</button>
          <button type="button" class="act-btn danger" title="删除团队" onclick="document.getElementById('delete-team-{{ $team->id }}').style.display='flex'">✕</button>
          <button type="button" class="add-inline-btn" style="margin-left:6px;" onclick="document.getElementById('add-spec-{{ $team->id }}').style.display='flex'">+ 专业</button>
        </div>

        @forelse($team->specialties as $specialty)
          <div class="spec-admin-row">
            <div class="spec-admin-head">
              <span class="sa-name">{{ $specialty->name }}</span>
              <span class="badge-count">{{ $specialty->subcategories_count }} 个细分类（全部项目累计）</span>
              <button type="button" class="act-btn" title="重命名专业" onclick="document.getElementById('rename-spec-{{ $specialty->id }}').style.display='flex'">✎</button>
              <button type="button" class="act-btn danger" title="删除专业" onclick="document.getElementById('delete-spec-{{ $specialty->id }}').style.display='flex'">✕</button>
            </div>
          </div>

          {{-- 专业重命名 --}}
          <div id="rename-spec-{{ $specialty->id }}" class="overlay" style="display:none;">
            <div class="modal">
              <div class="modal-head"><h3>重命名专业</h3><button type="button" class="modal-close" onclick="document.getElementById('rename-spec-{{ $specialty->id }}').style.display='none'">&times;</button></div>
              <form method="POST" action="{{ route('admin.specialties.update', $specialty) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                  <div class="field"><label>专业名称</label><input type="text" name="name" value="{{ $specialty->name }}" required></div>
                  <div class="field"><label>代码</label><input type="text" name="code" value="{{ $specialty->code }}"></div>
                </div>
                <div class="modal-foot">
                  <button type="button" class="btn btn-ghost" onclick="document.getElementById('rename-spec-{{ $specialty->id }}').style.display='none'">取消</button>
                  <button type="submit" class="btn btn-accent">保存</button>
                </div>
              </form>
            </div>
          </div>

          {{-- 专业删除 --}}
          <div id="delete-spec-{{ $specialty->id }}" class="overlay" style="display:none;">
            <div class="modal">
              <div class="modal-head"><h3>删除专业</h3><button type="button" class="modal-close" onclick="document.getElementById('delete-spec-{{ $specialty->id }}').style.display='none'">&times;</button></div>
              <form method="POST" action="{{ route('admin.specialties.destroy', $specialty) }}">
                @csrf @method('DELETE')
                <div class="modal-body">
                  <p style="font-size:13.5px;color:var(--ink-soft);">确定删除专业「{{ $specialty->name }}」吗？删除后将无法恢复。</p>
                  <p class="hint" style="color:var(--danger);">团队与专业为全公司统一标准分类，此操作会同时影响所有已有相关数据的项目，不只是当前项目。</p>
                  <div class="field"><label>请输入密码以确认删除</label><input type="password" name="password" required></div>
                </div>
                <div class="modal-foot">
                  <button type="button" class="btn btn-ghost" onclick="document.getElementById('delete-spec-{{ $specialty->id }}').style.display='none'">取消</button>
                  <button type="submit" class="btn" style="background:var(--danger);border-color:var(--danger);color:#fff;">确认删除</button>
                </div>
              </form>
            </div>
          </div>
        @empty
          <div style="padding:14px 16px;font-size:12.5px;color:var(--muted);">该团队下还没有专业</div>
        @endforelse
      </div>

      {{-- 添加专业 --}}
      <div id="add-spec-{{ $team->id }}" class="overlay" style="display:none;">
        <div class="modal">
          <div class="modal-head"><h3>添加专业</h3><button type="button" class="modal-close" onclick="document.getElementById('add-spec-{{ $team->id }}').style.display='none'">&times;</button></div>
          <form method="POST" action="{{ route('admin.specialties.store', $team) }}">
            @csrf
            <div class="modal-body">
              <div class="field"><label>专业名称</label><input type="text" name="name" placeholder="例如：给排水" required autofocus></div>
              <div class="field"><label>代码（可选）</label><input type="text" name="code" placeholder="例如：PS"></div>
            </div>
            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-spec-{{ $team->id }}').style.display='none'">取消</button>
              <button type="submit" class="btn btn-accent">添加</button>
            </div>
          </form>
        </div>
      </div>

      {{-- 团队重命名 --}}
      <div id="rename-team-{{ $team->id }}" class="overlay" style="display:none;">
        <div class="modal">
          <div class="modal-head"><h3>重命名团队</h3><button type="button" class="modal-close" onclick="document.getElementById('rename-team-{{ $team->id }}').style.display='none'">&times;</button></div>
          <form method="POST" action="{{ route('admin.teams.update', $team) }}">
            @csrf @method('PATCH')
            <div class="modal-body">
              <div class="field"><label>团队名称</label><input type="text" name="name" value="{{ $team->name }}" required></div>
            </div>
            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('rename-team-{{ $team->id }}').style.display='none'">取消</button>
              <button type="submit" class="btn btn-accent">保存</button>
            </div>
          </form>
        </div>
      </div>

      {{-- 团队删除 --}}
      <div id="delete-team-{{ $team->id }}" class="overlay" style="display:none;">
        <div class="modal">
          <div class="modal-head"><h3>删除团队</h3><button type="button" class="modal-close" onclick="document.getElementById('delete-team-{{ $team->id }}').style.display='none'">&times;</button></div>
          <form method="POST" action="{{ route('admin.teams.destroy', $team) }}">
            @csrf @method('DELETE')
            <div class="modal-body">
              <p style="font-size:13.5px;color:var(--ink-soft);">确定删除团队「{{ $team->name }}」吗？其下有 {{ $team->specialties->count() }} 个专业，删除后将无法恢复。</p>
              <p class="hint" style="color:var(--danger);">团队与专业为全公司统一标准分类，此操作会同时影响所有已有相关数据的项目，不只是当前项目。</p>
              <div class="field"><label>请输入密码以确认删除</label><input type="password" name="password" required></div>
            </div>
            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('delete-team-{{ $team->id }}').style.display='none'">取消</button>
              <button type="submit" class="btn" style="background:var(--danger);border-color:var(--danger);color:#fff;">确认删除</button>
            </div>
          </form>
        </div>
      </div>
    @endforeach

    {{-- 添加团队 --}}
    <div id="add-team-modal" class="overlay" style="display:none;">
      <div class="modal">
        <div class="modal-head"><h3>添加团队</h3><button type="button" class="modal-close" onclick="document.getElementById('add-team-modal').style.display='none'">&times;</button></div>
        <form method="POST" action="{{ route('admin.teams.store') }}">
          @csrf
          <div class="modal-body">
            <div class="field"><label>团队名称</label><input type="text" name="name" placeholder="例如：土建" required autofocus></div>
          </div>
          <div class="modal-foot">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-team-modal').style.display='none'">取消</button>
            <button type="submit" class="btn btn-accent">添加</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
