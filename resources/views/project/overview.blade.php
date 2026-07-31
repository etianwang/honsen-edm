@extends('layouts.app')
@section('title', $project->name.' · 总览')
@section('content')
<div class="shell">
  @include('partials.sidebar')

  <div class="main">
    <div class="main-inner">
      <h1 class="page-title">总览</h1>
      <p class="page-sub">Honsen Africa · {{ $project->name }} 图纸变更总览</p>

      <div class="stat-grid">
        <div class="stat-card"><div class="label">图纸分类总数</div><div class="value">{{ $stats['subcategories'] }}</div></div>
        <div class="stat-card"><div class="label">累计变更版本</div><div class="value">{{ $stats['versions'] }}</div></div>
        <div class="stat-card"><div class="label">本月新增变更</div><div class="value">{{ $stats['this_month'] }}</div></div>
        <div class="stat-card"><div class="label">含外发语言版本</div><div class="value">{{ $stats['external'] }}</div></div>
      </div>

      <h2 class="section-title">最新变更</h2>
      <div class="chip-row">
        <a href="{{ route('project.show', $project) }}" class="chip {{ request('team') ? '' : 'active' }}">全部</a>
        @foreach($tree as $team)
          <a href="{{ route('project.show', $project) }}?team={{ $team->id }}" class="chip {{ request('team') == $team->id ? 'active' : '' }}">{{ $team->name }}</a>
        @endforeach
      </div>

      @php
        $filtered = request('team') ? $feed->filter(fn($v) => $v->subcategory->specialty->team_id == request('team')) : $feed;
      @endphp

      @forelse($filtered as $version)
        @php($sub = $version->subcategory)
        <a href="{{ route('subcategory.show', [$project, $sub]) }}" class="feed-item">
          <div style="flex:1;min-width:0;">
            <div class="feed-crumb"><b>{{ $sub->specialty->team->name }}</b> / {{ $sub->specialty->name }} / <b>{{ $sub->name }}</b></div>
            <div class="feed-desc">{{ $version->description }}</div>
          </div>
          <div class="feed-meta">
            <div class="feed-ver">{{ $version->version_no }}</div>
            <div class="feed-date">{{ $version->publish_date->format('Y-m-d') }}</div>
            <div class="lang-badges feed-langs">
              @foreach(['fr','en'] as $lang)
                @php($present = $version->files->firstWhere('language', $lang))
                <span class="lang-pill {{ $present ? 'present' : 'missing' }}" style="font-size:9.5px;padding:1px 5px;">{{ strtoupper($lang) }}</span>
              @endforeach
            </div>
          </div>
        </a>
      @empty
        <div class="empty-state"><p>该团队暂无变更记录</p></div>
      @endforelse
    </div>
  </div>
</div>
@endsection
