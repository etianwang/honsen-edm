@extends('layouts.app')
@section('title', '站内通知')
@section('content')
<div class="admin-shell">
  <div class="admin-inner" style="max-width:680px;">
    <div class="admin-head-row">
      <div>
        <h1 class="page-title">站内通知</h1>
        <p class="page-sub" style="margin:0;">你所在项目 / 团队范围内的图纸变更提醒</p>
      </div>
      @if($notifications->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read-all') }}">
          @csrf
          <button type="submit" class="btn btn-sm">全部标记已读</button>
        </form>
      @endif
    </div>

    @if(session('toast'))<div class="admin-note" style="background:var(--success-bg);color:var(--success);">{{ session('toast') }}</div>@endif

    @forelse($notifications as $n)
      <form method="POST" action="{{ route('notifications.read', $n->id) }}">
        @csrf
        <button type="submit" class="feed-item" style="width:100%;text-align:left;border:1px solid var(--border);{{ $n->read_at ? '' : 'border-left:4px solid var(--accent);' }}">
          <div style="flex:1;min-width:0;">
            <div class="feed-crumb"><b>{{ $n->data['team_name'] }}</b> / {{ $n->data['specialty_name'] }} / <b>{{ $n->data['subcategory_name'] }}</b></div>
            <div class="feed-desc">{{ $n->data['description'] }}</div>
            <div class="hint" style="margin-top:4px;">{{ $n->data['uploader_name'] ?? '—' }} 发布 · {{ $n->created_at->diffForHumans() }}</div>
          </div>
          <div class="feed-meta">
            <div class="feed-ver">{{ $n->data['version_no'] }}</div>
            @unless($n->read_at)<span class="badge-latest" style="margin-top:6px;display:inline-block;">未读</span>@endunless
          </div>
        </button>
      </form>
    @empty
      <div class="empty-state"><p>暂时没有通知</p></div>
    @endforelse

    <div style="margin-top:16px;">{{ $notifications->links() }}</div>
  </div>
</div>
@endsection
