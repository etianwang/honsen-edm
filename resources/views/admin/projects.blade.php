@extends('layouts.app')
@section('title', '国家 / 项目管理')
@section('content')
<div class="admin-shell">
  <div class="admin-inner">
    <a href="{{ route('admin.index') }}" style="font-size:12.5px;color:var(--muted);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:14px;">← 返回后台首页</a>
    <div class="admin-head-row">
      <div>
        <h1 class="page-title">国家 / 项目管理</h1>
        <p class="page-sub" style="margin:0;">项目是账号可见性和图纸数据的边界，删除前请确认清楚影响范围</p>
      </div>
      <button type="button" class="btn btn-accent" onclick="document.getElementById('add-country-modal').style.display='flex'">+ 添加国家</button>
    </div>

    @if(session('toast'))<div class="admin-note" style="background:var(--success-bg);color:var(--success);">{{ session('toast') }}</div>@endif
    @if($errors->any())<div class="admin-note" style="background:var(--danger-bg);color:var(--danger);">{{ $errors->first() }}</div>@endif

    @forelse($countries as $country)
      <div class="team-card">
        <div class="team-card-head">
          <span class="tc-name">{{ $country->name }}</span>
          <span class="badge-count">{{ $country->projects_count }} 个项目</span>
          <button type="button" class="act-btn" title="重命名国家" onclick="document.getElementById('rename-country-{{ $country->id }}').style.display='flex'">✎</button>
          <button type="button" class="act-btn danger" title="删除国家" onclick="document.getElementById('delete-country-{{ $country->id }}').style.display='flex'">✕</button>
          <button type="button" class="add-inline-btn" style="margin-left:6px;" onclick="document.getElementById('add-project-{{ $country->id }}').style.display='flex'">+ 项目</button>
        </div>

        @forelse($country->projects as $project)
          <div class="spec-admin-row">
            <div class="spec-admin-head">
              <span class="sa-name">{{ $project->name }}</span>
              <span class="badge-count">{{ $project->subcategories_count }} 个细分类</span>
              <button type="button" class="act-btn" title="重命名项目" onclick="document.getElementById('rename-project-{{ $project->id }}').style.display='flex'">✎</button>
              <button type="button" class="act-btn danger" title="删除项目" onclick="document.getElementById('delete-project-{{ $project->id }}').style.display='flex'">✕</button>
            </div>
          </div>

          {{-- 项目重命名 --}}
          <div id="rename-project-{{ $project->id }}" class="overlay" style="display:none;">
            <div class="modal">
              <div class="modal-head"><h3>重命名项目</h3><button type="button" class="modal-close" onclick="document.getElementById('rename-project-{{ $project->id }}').style.display='none'">&times;</button></div>
              <form method="POST" action="{{ route('admin.projects.update', $project) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                  <div class="field"><label>项目名称</label><input type="text" name="name" value="{{ $project->name }}" required></div>
                </div>
                <div class="modal-foot">
                  <button type="button" class="btn btn-ghost" onclick="document.getElementById('rename-project-{{ $project->id }}').style.display='none'">取消</button>
                  <button type="submit" class="btn btn-accent">保存</button>
                </div>
              </form>
            </div>
          </div>

          {{-- 项目删除 --}}
          <div id="delete-project-{{ $project->id }}" class="overlay" style="display:none;">
            <div class="modal">
              <div class="modal-head"><h3>删除项目</h3><button type="button" class="modal-close" onclick="document.getElementById('delete-project-{{ $project->id }}').style.display='none'">&times;</button></div>
              <form method="POST" action="{{ route('admin.projects.destroy', $project) }}">
                @csrf @method('DELETE')
                <div class="modal-body">
                  <p style="font-size:13.5px;color:var(--ink-soft);">确定删除项目「{{ $project->name }}」吗？其下有 {{ $project->subcategories_count }} 个细分类，删除后这些数据都会一并不再可见。</p>
                  <div class="field"><label>请输入密码以确认删除</label><input type="password" name="password" required></div>
                </div>
                <div class="modal-foot">
                  <button type="button" class="btn btn-ghost" onclick="document.getElementById('delete-project-{{ $project->id }}').style.display='none'">取消</button>
                  <button type="submit" class="btn" style="background:var(--danger);border-color:var(--danger);color:#fff;">确认删除</button>
                </div>
              </form>
            </div>
          </div>
        @empty
          <div style="padding:14px 16px;font-size:12.5px;color:var(--muted);">该国家下还没有项目</div>
        @endforelse
      </div>

      {{-- 添加项目 --}}
      <div id="add-project-{{ $country->id }}" class="overlay" style="display:none;">
        <div class="modal">
          <div class="modal-head"><h3>在「{{ $country->name }}」下添加项目</h3><button type="button" class="modal-close" onclick="document.getElementById('add-project-{{ $country->id }}').style.display='none'">&times;</button></div>
          <form method="POST" action="{{ route('admin.projects.store', $country) }}">
            @csrf
            <div class="modal-body">
              <div class="field"><label>项目名称</label><input type="text" name="name" placeholder="例如：Bethel办公楼" required autofocus></div>
            </div>
            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-project-{{ $country->id }}').style.display='none'">取消</button>
              <button type="submit" class="btn btn-accent">添加</button>
            </div>
          </form>
        </div>
      </div>

      {{-- 国家重命名 --}}
      <div id="rename-country-{{ $country->id }}" class="overlay" style="display:none;">
        <div class="modal">
          <div class="modal-head"><h3>重命名国家</h3><button type="button" class="modal-close" onclick="document.getElementById('rename-country-{{ $country->id }}').style.display='none'">&times;</button></div>
          <form method="POST" action="{{ route('admin.countries.update', $country) }}">
            @csrf @method('PATCH')
            <div class="modal-body">
              <div class="field"><label>国家名称</label><input type="text" name="name" value="{{ $country->name }}" required></div>
            </div>
            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('rename-country-{{ $country->id }}').style.display='none'">取消</button>
              <button type="submit" class="btn btn-accent">保存</button>
            </div>
          </form>
        </div>
      </div>

      {{-- 国家删除 --}}
      <div id="delete-country-{{ $country->id }}" class="overlay" style="display:none;">
        <div class="modal">
          <div class="modal-head"><h3>删除国家</h3><button type="button" class="modal-close" onclick="document.getElementById('delete-country-{{ $country->id }}').style.display='none'">&times;</button></div>
          <form method="POST" action="{{ route('admin.countries.destroy', $country) }}">
            @csrf @method('DELETE')
            <div class="modal-body">
              <p style="font-size:13.5px;color:var(--ink-soft);">确定删除国家「{{ $country->name }}」吗？其下有 {{ $country->projects_count }} 个项目，删除后这些项目都会一并不再可见。</p>
              <div class="field"><label>请输入密码以确认删除</label><input type="password" name="password" required></div>
            </div>
            <div class="modal-foot">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('delete-country-{{ $country->id }}').style.display='none'">取消</button>
              <button type="submit" class="btn" style="background:var(--danger);border-color:var(--danger);color:#fff;">确认删除</button>
            </div>
          </form>
        </div>
      </div>
    @empty
      <div class="empty-state">
        <p>还没有任何国家 / 项目，先添加一个</p>
      </div>
    @endforelse

    <div id="add-country-modal" class="overlay" style="display:none;">
      <div class="modal">
        <div class="modal-head"><h3>添加国家</h3><button type="button" class="modal-close" onclick="document.getElementById('add-country-modal').style.display='none'">&times;</button></div>
        <form method="POST" action="{{ route('admin.countries.store') }}">
          @csrf
          <div class="modal-body">
            <div class="field"><label>国家名称</label><input type="text" name="name" placeholder="例如：科特迪瓦" required autofocus></div>
          </div>
          <div class="modal-foot">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-country-modal').style.display='none'">取消</button>
            <button type="submit" class="btn btn-accent">添加</button>
          </div>
        </form>
      </div>
    </div>

    @include('partials.footer')
  </div>
</div>
@endsection
