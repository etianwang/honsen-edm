@extends('layouts.app')
@section('title', $subcategory->name.' · '.$project->name)
@section('content')
@php
  $langLabel = ['zh' => __('中文'), 'fr' => __('法语'), 'en' => __('英语')];
  $langShort = ['zh' => __('中'), 'fr' => 'FR', 'en' => 'EN'];
  $versions = $subcategory->versions;
  $latest = $versions->first();
  $history = $versions->slice(1);
@endphp
<div class="shell">
  @include('partials.sidebar')

  <div class="main">
    <div class="main-inner">
      <div class="crumb-nav">
        <a href="{{ route('project.show', $project) }}">{{ __('总览') }}</a> /
        <a href="{{ route('project.team', [$project, $subcategory->specialty->team]) }}">{{ $subcategory->specialty->team->name }}</a> /
        <a href="{{ route('project.specialty', [$project, $subcategory->specialty]) }}">{{ $subcategory->specialty->name }}</a> /
        <b>{{ $subcategory->name }}</b>
      </div>

      <div class="detail-header">
        <div>
          <h1 class="page-title" style="display:inline;">{{ $subcategory->name }}</h1>
          <span class="tag-pill">{{ $subcategory->specialty->team->name }} · {{ $subcategory->specialty->name }}</span>
        </div>
        <div class="detail-actions">
          @if($user->canManageContent())
            <button type="button" class="btn btn-sm" onclick="document.getElementById('rename-modal').style.display='flex'">{{ __('重命名') }}</button>
            <button type="button" class="btn btn-sm" style="color:var(--danger);" onclick="document.getElementById('delete-sub-modal').style.display='flex'">{{ __('删除细分类') }}</button>
            <button type="button" class="btn btn-accent" onclick="document.getElementById('upload-modal').style.display='flex'">{{ __('上传变更') }}</button>
          @endif
        </div>
      </div>

      @if($versions->isEmpty())
        <div class="empty-state">
          <p>{{ __('该细分类暂无变更记录') }}</p>
          @if($user->canManageContent())
            <button type="button" class="btn btn-accent" onclick="document.getElementById('upload-modal').style.display='flex'">{{ __('上传首个变更') }}</button>
          @endif
        </div>
      @else
        <div class="latest-card">
          <div class="latest-top">
            <span class="badge-latest">{{ __('最新版本') }}</span>
            <span class="ver-mono">{{ $latest->version_no }}</span>
            <span class="date-mono">{{ $latest->publish_date->format('Y-m-d') }} {{ __('发布') }}</span>
            <div class="spacer"></div>
            <div class="ops-row">
              @if($user->canManageContent())
                <form method="POST" action="{{ route('version.destroy', $latest) }}" id="del-version-{{ $latest->id }}">@csrf @method('DELETE')</form>
                <button type="button" class="icon-btn danger" title="{{ __('删除') }}"
                  @click="$dispatch('confirm-action', {formId: 'del-version-{{ $latest->id }}', message: '{{ __('确定删除该版本记录（含所有语言文件）？此操作不可撤销。') }}'})">✕</button>
              @endif
            </div>
          </div>
          <p class="latest-desc">{{ $latest->description }}</p>
          <div class="latest-row2">
            @php($zhDwgCount = $latest->drawingsFor('zh', \App\Models\VersionDrawing::KIND_DWG)->count())
            @php($zhPdfCount = $latest->drawingsFor('zh', \App\Models\VersionDrawing::KIND_PDF)->count())
            <div class="file-chips">
              <span class="file-chip" style="cursor:pointer;" onclick="document.getElementById('lang-modal-{{ $latest->id }}-zh').style.display='flex'">DWG × {{ $zhDwgCount }}</span>
              @if($zhPdfCount > 0)
                <span class="file-chip" style="cursor:pointer;" onclick="document.getElementById('lang-modal-{{ $latest->id }}-zh').style.display='flex'">PDF × {{ $zhPdfCount }}</span>
              @endif
              @php($zhDoc = $latest->fileFor('zh'))
              @if($zhDoc?->doc_path)
                <a class="file-chip" href="{{ route('version-file.download', [$latest, 'zh']) }}"><span class="fname">{{ basename($zhDoc->doc_path) }}</span></a>
              @endif
            </div>
            @php($availableLangs = $latest->availableLanguages())
            <div class="lang-badges">
              @foreach(['zh','fr','en'] as $lang)
                @if($availableLangs->contains($lang))
                  <span class="lang-pill present" style="cursor:pointer;" onclick="document.getElementById('lang-modal-{{ $latest->id }}-{{ $lang }}').style.display='flex'" title="{{ $langLabel[$lang] }} {{ __('版本') }} · {{ __('点击管理文件') }}">{{ $langShort[$lang] }}</span>
                @elseif($user->canManageContent())
                  <span class="lang-pill missing addable" onclick="document.getElementById('lang-modal-{{ $latest->id }}-{{ $lang }}').style.display='flex'" title="{{ __('补充') }} {{ $langLabel[$lang] }} {{ __('版本') }}">+{{ $langShort[$lang] }}</span>
                @else
                  <span class="lang-pill missing" title="{{ $langLabel[$lang] }} {{ __('版本暂无') }}">{{ $langShort[$lang] }}</span>
                @endif
              @endforeach
            </div>
          </div>
          <div class="latest-foot">
            <span>{{ __('上传人') }}：{{ $latest->uploader?->name ?? '—' }}</span>
            <span>{{ __('发布日期') }}：{{ $latest->publish_date->format('Y-m-d') }}</span>
          </div>
        </div>

        @foreach(['zh','fr','en'] as $lang)
          @include('project.partials.lang-modal', ['version' => $latest, 'lang' => $lang, 'langLabel' => $langLabel])
        @endforeach

        <h2 class="section-title">{{ __('历史版本') }}</h2>
        @if($history->isEmpty())
          <div class="empty-state" style="padding:30px 20px;"><p style="margin:0;">{{ __('暂无更早的历史版本') }}</p></div>
        @else
          <table class="revlog">
            <thead><tr><th style="width:56px;">{{ __('版本') }}</th><th style="width:92px;">{{ __('日期') }}</th><th style="width:110px;">{{ __('语言版本') }}</th><th>{{ __('变更说明') }}</th><th style="width:64px;">{{ __('上传人') }}</th><th style="width:80px;">{{ __('操作') }}</th></tr></thead>
            <tbody>
              @foreach($history as $v)
                <tr>
                  <td class="mono">{{ $v->version_no }}</td>
                  <td class="mono">{{ $v->publish_date->format('Y-m-d') }}</td>
                  <td>
                    @php($vAvailableLangs = $v->availableLanguages())
                    <div class="lang-badges">
                      @foreach(['zh','fr','en'] as $lang)
                        <span class="lang-pill {{ $vAvailableLangs->contains($lang) ? 'present' : 'missing' }}">{{ $langShort[$lang] }}</span>
                      @endforeach
                    </div>
                  </td>
                  <td class="desc-cell">{{ $v->description }}</td>
                  <td class="mono">{{ $v->uploader?->name ?? '—' }}</td>
                  <td class="ops">
                    <div class="ops-row">
                      @if($vAvailableLangs->contains('zh'))
                        <a class="icon-btn" title="{{ __('下载中文全部文件（ZIP）') }}" href="{{ route('version.language-zip', [$v, 'zh']) }}">↓</a>
                      @endif
                      @if($user->canManageContent())
                        <form method="POST" action="{{ route('version.destroy', $v) }}" id="del-version-{{ $v->id }}">@csrf @method('DELETE')</form>
                        <button type="button" class="icon-btn danger" title="{{ __('删除') }}"
                          @click="$dispatch('confirm-action', {formId: 'del-version-{{ $v->id }}', message: '{{ __('确定删除该版本记录（含所有语言文件）？此操作不可撤销。') }}'})">✕</button>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      @endif

      @include('partials.footer')
    </div>
  </div>
</div>

@if($user->canManageContent())
{{-- 上传变更 --}}
<div id="upload-modal" class="overlay" style="display:none;">
  <div class="modal wide">
    <div class="modal-head"><h3>{{ __('上传变更') }}</h3><button type="button" class="modal-close" onclick="document.getElementById('upload-modal').style.display='none'">&times;</button></div>
    <form method="POST" action="{{ route('version.store', $subcategory) }}" enctype="multipart/form-data" class="async-upload-form" data-upload-label="{{ __('发布变更') }} · {{ $subcategory->name }}">
      @csrf
      <div class="modal-body">
        <div class="field"><label>{{ __('图纸分类') }}</label><input type="text" value="{{ $subcategory->specialty->team->name }} / {{ $subcategory->specialty->name }} / {{ $subcategory->name }}" disabled style="background:var(--paper);color:var(--muted);"></div>
        <div class="field-row">
          <div class="field"><label>{{ __('版本号') }}</label><input type="text" name="version_no" value="V{{ $versions->count() + 1 }}" required></div>
          <div class="field"><label>{{ __('发布日期') }}</label><input type="date" name="publish_date" value="{{ now()->format('Y-m-d') }}" required></div>
        </div>
        <div class="field"><label>{{ __('变更说明（中文）') }}</label><textarea name="description" placeholder="{{ __('简要描述本次变更内容...') }}" required></textarea></div>

        <p class="hint" style="margin-top:0;">{{ __('文件大小上限：DWG/DXF 单份 :dwg，PDF 单份 :pdf，说明文件单份 :doc（doc/docx/xls/xlsx/pdf/txt/dwg 任一格式）。', ['dwg' => round(config('uploads.dwg_max_kb') / 1024).'MB', 'pdf' => round(config('uploads.pdf_max_kb') / 1024).'MB', 'doc' => round(config('uploads.doc_max_kb') / 1024).'MB']) }}</p>

        <div class="lang-section zh">
          <div class="lang-section-head">{{ __('中文（DWG 必填 · 内部使用）') }}</div>
          <div class="field-row">
            <div class="field" style="margin-bottom:0;"><label>{{ __('图纸文件（DWG，可多选，比如每层一张）') }}</label><input type="file" name="zh_dwg[]" accept=".dwg,.dxf" multiple required></div>
            <div class="field" style="margin-bottom:0;"><label>{{ __('PDF 图纸（可选，可多选）') }}</label><input type="file" name="zh_pdf[]" accept=".pdf" multiple></div>
          </div>
          <div class="field" style="margin-bottom:0;margin-top:12px;"><label>{{ __('变更说明文件（可选，doc/docx/xls/xlsx/pdf/txt/dwg 任一）') }}</label><input type="file" name="zh_doc" accept=".doc,.docx,.xls,.xlsx,.pdf,.txt,.dwg"></div>
        </div>
        <div class="lang-section">
          <div class="lang-section-head">{{ __('法语（可选 · 外发时使用）') }}</div>
          <div class="field-row">
            <div class="field" style="margin-bottom:0;"><label>{{ __('图纸文件（DWG，可多选）') }}</label><input type="file" name="fr_dwg[]" accept=".dwg,.dxf" multiple></div>
            <div class="field" style="margin-bottom:0;"><label>{{ __('PDF 图纸（可选，可多选）') }}</label><input type="file" name="fr_pdf[]" accept=".pdf" multiple></div>
          </div>
          <div class="field" style="margin-bottom:0;margin-top:12px;"><label>{{ __('变更说明文件（可选）') }}</label><input type="file" name="fr_doc" accept=".doc,.docx,.xls,.xlsx,.pdf,.txt,.dwg"></div>
        </div>
        <div class="lang-section">
          <div class="lang-section-head">{{ __('英语（可选 · 外发时使用）') }}</div>
          <div class="field-row">
            <div class="field" style="margin-bottom:0;"><label>{{ __('图纸文件（DWG，可多选）') }}</label><input type="file" name="en_dwg[]" accept=".dwg,.dxf" multiple></div>
            <div class="field" style="margin-bottom:0;"><label>{{ __('PDF 图纸（可选，可多选）') }}</label><input type="file" name="en_pdf[]" accept=".pdf" multiple></div>
          </div>
          <div class="field" style="margin-bottom:0;margin-top:12px;"><label>{{ __('变更说明文件（可选）') }}</label><input type="file" name="en_doc" accept=".doc,.docx,.xls,.xlsx,.pdf,.txt,.dwg"></div>
        </div>
        <p class="hint">{{ __('法语 / 英语版本仅在该图纸需要外发时上传；内部使用的图纸只需上传中文 DWG，其余都可以之后随时在版本记录里补充。DWG / PDF 都支持一次选多个文件，也可以之后单独追加或删除某一份。') }}</p>
        @error('zh_dwg')<p class="error-text">{{ $message }}</p>@enderror
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('upload-modal').style.display='none'">{{ __('取消') }}</button>
        <button type="submit" class="btn btn-accent">{{ __('发布变更') }}</button>
      </div>
    </form>
  </div>
</div>

{{-- 重命名细分类 --}}
<div id="rename-modal" class="overlay" style="display:none;">
  <div class="modal">
    <div class="modal-head"><h3>{{ __('重命名细分类') }}</h3><button type="button" class="modal-close" onclick="document.getElementById('rename-modal').style.display='none'">&times;</button></div>
    <form method="POST" action="{{ route('subcategory.update', $subcategory) }}">
      @csrf @method('PATCH')
      <div class="modal-body">
        <div class="field"><label>{{ __('细分类名称') }}</label><input type="text" name="name" value="{{ $subcategory->name }}" required></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('rename-modal').style.display='none'">{{ __('取消') }}</button>
        <button type="submit" class="btn btn-accent">{{ __('保存') }}</button>
      </div>
    </form>
  </div>
</div>

{{-- 删除细分类（密码确认）--}}
<div id="delete-sub-modal" class="overlay" style="display:none;">
  <div class="modal">
    <div class="modal-head"><h3>{{ __('删除细分类') }}</h3><button type="button" class="modal-close" onclick="document.getElementById('delete-sub-modal').style.display='none'">&times;</button></div>
    <form method="POST" action="{{ route('subcategory.destroy', $subcategory) }}">
      @csrf @method('DELETE')
      <div class="modal-body">
        <p style="font-size:13.5px;color:var(--ink-soft);margin:0 0 14px;">{{ __('确定删除') }}「{{ $subcategory->name }}」{{ __('这个细分类吗？其下有') }} {{ $versions->count() }} {{ __('条变更记录，删除后将无法恢复。') }}</p>
        <div class="field">
          <label>{{ __('请输入密码以确认删除') }}</label>
          <input type="password" name="password" placeholder="{{ __('密码') }}" autocomplete="off" required>
        </div>
        @error('password')<p class="error-text">{{ $message }}</p>@enderror
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('delete-sub-modal').style.display='none'">{{ __('取消') }}</button>
        <button type="submit" class="btn" style="background:var(--danger);border-color:var(--danger);color:#fff;">{{ __('确认删除') }}</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection
