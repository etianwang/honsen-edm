@extends('layouts.app')
@section('title', $specialty->name.' · '.$project->name)
@section('content')
<div class="shell">
  @include('partials.sidebar')

  <div class="main">
    <div class="main-inner">
      <div class="crumb-nav">
        <a href="{{ route('project.show', $project) }}">{{ __('总览') }}</a> /
        <a href="{{ route('project.team', [$project, $specialty->team]) }}">{{ $specialty->team->name }}</a> /
        <b>{{ $specialty->name }}</b>
      </div>

      <h1 class="page-title">{{ $specialty->name }}</h1>
      <p class="page-sub">{{ $project->name }} · {{ $specialty->team->name }} · {{ __('专业图纸变更总览') }}</p>

      <div class="stat-grid">
        <div class="stat-card"><div class="label">{{ __('图纸分类总数') }}</div><div class="value">{{ $stats['subcategories'] }}</div></div>
        <div class="stat-card"><div class="label">{{ __('累计变更版本') }}</div><div class="value">{{ $stats['versions'] }}</div></div>
        <div class="stat-card"><div class="label">{{ __('本月新增变更') }}</div><div class="value">{{ $stats['this_month'] }}</div></div>
        <div class="stat-card"><div class="label">{{ __('含外发语言版本') }}</div><div class="value">{{ $stats['external'] }}</div></div>
      </div>

      @if($specialtyNode->subcategories->isNotEmpty())
        <h2 class="section-title">{{ __('细分类') }}</h2>
        <div class="sub-grid" style="margin-bottom:28px;">
          @foreach($specialtyNode->subcategories as $sub)
            <a href="{{ route('subcategory.show', [$project, $sub]) }}" class="sub-tile">
              <span class="sub-tile-name">{{ $sub->name }}</span>
              <span class="sub-tile-count">{{ $sub->versions_count }}</span>
            </a>
          @endforeach
        </div>
      @endif

      <h2 class="section-title">{{ __('最新变更') }}</h2>
      @forelse($feed as $version)
        @php($sub = $version->subcategory)
        <a href="{{ route('subcategory.show', [$project, $sub]) }}" class="feed-item">
          <div style="flex:1;min-width:0;">
            <div class="feed-crumb"><b>{{ $sub->name }}</b></div>
            <div class="feed-desc">{{ $version->description }}</div>
          </div>
          <div class="feed-meta">
            <div class="feed-ver">{{ $version->version_no }}</div>
            <div class="feed-date">{{ $version->publish_date->format('Y-m-d') }}</div>
            <div class="lang-badges feed-langs">
              @foreach(['fr','en'] as $lang)
                @php($present = $version->availableLanguages()->contains($lang))
                <span class="lang-pill {{ $present ? 'present' : 'missing' }}" style="font-size:9.5px;padding:1px 5px;">{{ strtoupper($lang) }}</span>
              @endforeach
            </div>
          </div>
        </a>
      @empty
        <div class="empty-state"><p>{{ __('该专业暂无变更记录') }}</p></div>
      @endforelse
    </div>
  </div>
</div>
@endsection
