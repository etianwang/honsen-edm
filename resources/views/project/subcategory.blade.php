@extends('layouts.app')
@section('title', $subcategory->name.' · '.$project->name)
@section('content')
@php
  $langLabel = ['zh' => '中文', 'fr' => '法语', 'en' => '英语'];
  $langShort = ['zh' => '中', 'fr' => 'FR', 'en' => 'EN'];
  $versions = $subcategory->versions;
  $latest = $versions->first();
  $history = $versions->slice(1);
  $formatSize = function ($bytes) {
      if (! $bytes) return '—';
      return $bytes >= 1048576 ? round($bytes / 1048576, 1).'MB' : round($bytes / 1024).'KB';
  };
@endphp
<div class="shell">
  @include('partials.sidebar')

  <div class="main">
    <div class="main-inner">
      <div class="crumb-nav">
        <a href="{{ route('project.show', $project) }}">总览</a> / {{ $subcategory->specialty->team->name }} / {{ $subcategory->specialty->name }} / <b>{{ $subcategory->name }}</b>
      </div>

      <div class="detail-header">
        <div>
          <h1 class="page-title" style="display:inline;">{{ $subcategory->name }}</h1>
          <span class="tag-pill">{{ $subcategory->specialty->team->name }} · {{ $subcategory->specialty->name }}</span>
        </div>
        <div class="detail-actions">
          @if($user->canManageContent())
            <button type="button" class="btn btn-sm" onclick="document.getElementById('rename-modal').style.display='flex'">重命名</button>
            <button type="button" class="btn btn-sm" style="color:var(--danger);" onclick="document.getElementById('delete-sub-modal').style.display='flex'">删除细分类</button>
            <button type="button" class="btn btn-accent" onclick="document.getElementById('upload-modal').style.display='flex'">上传变更</button>
          @endif
        </div>
      </div>

      @if($versions->isEmpty())
        <div class="empty-state">
          <p>该细分类暂无变更记录</p>
          @if($user->canManageContent())
            <button type="button" class="btn btn-accent" onclick="document.getElementById('upload-modal').style.display='flex'">上传首个变更</button>
          @endif
        </div>
      @else
        <div class="latest-card">
          <div class="latest-top">
            <span class="badge-latest">最新版本</span>
            <span class="ver-mono">{{ $latest->version_no }}</span>
            <span class="date-mono">{{ $latest->publish_date->format('Y-m-d') }} 发布</span>
            <div class="spacer"></div>
            <div class="ops-row">
              @php($firstPreviewLang = collect(['zh','fr','en'])->first(fn($l) => $latest->fileFor($l)))
              @php($firstPreviewFile = $firstPreviewLang ? $latest->fileFor($firstPreviewLang) : null)
              <button type="button" class="icon-btn" title="预览"
                onclick="document.getElementById('preview-modal-{{ $latest->id }}').style.display='flex'; honsenLoadDxfPreview('{{ $latest->id }}', '{{ $firstPreviewLang }}', {{ json_encode($firstPreviewFile?->hasInteractivePreview() ?? false) }}, {{ json_encode($firstPreviewFile ? route('version-file.dxf', [$latest, $firstPreviewLang]) : null) }})">👁</button>
              @if($user->canManageContent())
                <form method="POST" action="{{ route('version.destroy', $latest) }}" onsubmit="return confirm('确定删除该版本记录（含所有语言文件）？此操作不可撤销。')">
                  @csrf @method('DELETE')
                  <button type="submit" class="icon-btn danger" title="删除">✕</button>
                </form>
              @endif
            </div>
          </div>
          <p class="latest-desc">{{ $latest->description }}</p>
          <div class="latest-row2">
            @php($zh = $latest->fileFor('zh'))
            <div class="file-chips">
              @if($zh?->dwg_path)
                <a class="file-chip" href="{{ route('version-file.download', [$latest, 'zh', 'dwg']) }}"><span class="fname">{{ basename($zh->dwg_path) }}</span><span style="color:var(--muted)">{{ $formatSize($zh->dwg_size) }}</span></a>
              @endif
              @if($zh?->doc_path)
                <a class="file-chip" href="{{ route('version-file.download', [$latest, 'zh', 'doc']) }}"><span class="fname">{{ basename($zh->doc_path) }}</span><span style="color:var(--muted)">{{ $formatSize($zh->doc_size) }}</span></a>
              @endif
            </div>
            <div class="lang-badges">
              @foreach(['zh','fr','en'] as $lang)
                @php($file = $latest->fileFor($lang))
                @if($file)
                  <span class="lang-pill present" style="cursor:pointer;" onclick="document.getElementById('lang-modal-{{ $latest->id }}-{{ $lang }}').style.display='flex'" title="{{ $langLabel[$lang] }}版本 · 点击替换 / 移除">{{ $langShort[$lang] }}</span>
                @elseif($user->canManageContent())
                  <span class="lang-pill missing addable" onclick="document.getElementById('lang-modal-{{ $latest->id }}-{{ $lang }}').style.display='flex'" title="补充{{ $langLabel[$lang] }}版本">+{{ $langShort[$lang] }}</span>
                @else
                  <span class="lang-pill missing" title="{{ $langLabel[$lang] }}版本暂无">{{ $langShort[$lang] }}</span>
                @endif
              @endforeach
            </div>
          </div>
          <div class="latest-foot">
            <span>上传人：{{ $latest->uploader?->name ?? '—' }}</span>
            <span>发布日期：{{ $latest->publish_date->format('Y-m-d') }}</span>
          </div>
        </div>

        @foreach(['zh','fr','en'] as $lang)
          @include('project.partials.lang-modal', ['version' => $latest, 'lang' => $lang, 'langLabel' => $langLabel])
        @endforeach
        @include('project.partials.preview-modal', ['version' => $latest])

        <h2 class="section-title">历史版本</h2>
        @if($history->isEmpty())
          <div class="empty-state" style="padding:30px 20px;"><p style="margin:0;">暂无更早的历史版本</p></div>
        @else
          <table class="revlog">
            <thead><tr><th style="width:56px;">版本</th><th style="width:92px;">日期</th><th style="width:110px;">语言版本</th><th>变更说明</th><th style="width:64px;">上传人</th><th style="width:80px;">操作</th></tr></thead>
            <tbody>
              @foreach($history as $v)
                <tr>
                  <td class="mono">{{ $v->version_no }}</td>
                  <td class="mono">{{ $v->publish_date->format('Y-m-d') }}</td>
                  <td>
                    <div class="lang-badges">
                      @foreach(['zh','fr','en'] as $lang)
                        @php($file = $v->fileFor($lang))
                        <span class="lang-pill {{ $file ? 'present' : 'missing' }}">{{ $langShort[$lang] }}</span>
                      @endforeach
                    </div>
                  </td>
                  <td class="desc-cell">{{ $v->description }}</td>
                  <td class="mono">{{ $v->uploader?->name ?? '—' }}</td>
                  <td class="ops">
                    <div class="ops-row">
                      @php($vFirstLang = collect(['zh','fr','en'])->first(fn($l) => $v->fileFor($l)))
                      @php($vFirstFile = $vFirstLang ? $v->fileFor($vFirstLang) : null)
                      <button type="button" class="icon-btn" title="预览"
                        onclick="document.getElementById('preview-modal-{{ $v->id }}').style.display='flex'; honsenLoadDxfPreview('{{ $v->id }}', '{{ $vFirstLang }}', {{ json_encode($vFirstFile?->hasInteractivePreview() ?? false) }}, {{ json_encode($vFirstFile ? route('version-file.dxf', [$v, $vFirstLang]) : null) }})">👁</button>
                      @include('project.partials.preview-modal', ['version' => $v])
                      @php($vzh = $v->fileFor('zh'))
                      @if($vzh?->dwg_path)
                        <a class="icon-btn" title="下载图纸" href="{{ route('version-file.download', [$v, 'zh', 'dwg']) }}">↓</a>
                      @endif
                      @if($user->canManageContent())
                        <form method="POST" action="{{ route('version.destroy', $v) }}" onsubmit="return confirm('确定删除该版本记录（含所有语言文件）？此操作不可撤销。')">
                          @csrf @method('DELETE')
                          <button type="submit" class="icon-btn danger" title="删除">✕</button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      @endif
    </div>
  </div>
</div>

@if($user->canManageContent())
{{-- 上传变更 --}}
<div id="upload-modal" class="overlay" style="display:none;">
  <div class="modal wide">
    <div class="modal-head"><h3>上传变更</h3><button type="button" class="modal-close" onclick="document.getElementById('upload-modal').style.display='none'">&times;</button></div>
    <form method="POST" action="{{ route('version.store', $subcategory) }}" enctype="multipart/form-data" class="async-upload-form" data-upload-label="发布变更 · {{ $subcategory->name }}">
      @csrf
      <div class="modal-body">
        <div class="field"><label>图纸分类</label><input type="text" value="{{ $subcategory->specialty->team->name }} / {{ $subcategory->specialty->name }} / {{ $subcategory->name }}" disabled style="background:var(--paper);color:var(--muted);"></div>
        <div class="field-row">
          <div class="field"><label>版本号</label><input type="text" name="version_no" value="V{{ $versions->count() + 1 }}" required></div>
          <div class="field"><label>发布日期</label><input type="date" name="publish_date" value="{{ now()->format('Y-m-d') }}" required></div>
        </div>
        <div class="field"><label>变更说明（中文）</label><textarea name="description" placeholder="简要描述本次变更内容..." required></textarea></div>

        <div class="lang-section zh">
          <div class="lang-section-head">中文（必填 · 内部使用）</div>
          <div class="field-row">
            <div class="field" style="margin-bottom:0;"><label>图纸文件（DWG）</label><input type="file" name="zh_dwg" accept=".dwg,.dxf" required></div>
            <div class="field" style="margin-bottom:0;"><label>变更说明文件</label><input type="file" name="zh_doc" accept=".doc,.docx,.xls,.xlsx,.pdf" required></div>
          </div>
        </div>
        <div class="lang-section">
          <div class="lang-section-head">法语（可选 · 外发时使用）</div>
          <div class="field-row">
            <div class="field" style="margin-bottom:0;"><label>图纸文件（DWG）</label><input type="file" name="fr_dwg" accept=".dwg,.dxf"></div>
            <div class="field" style="margin-bottom:0;"><label>变更说明文件</label><input type="file" name="fr_doc" accept=".doc,.docx,.xls,.xlsx,.pdf"></div>
          </div>
        </div>
        <div class="lang-section">
          <div class="lang-section-head">英语（可选 · 外发时使用）</div>
          <div class="field-row">
            <div class="field" style="margin-bottom:0;"><label>图纸文件（DWG）</label><input type="file" name="en_dwg" accept=".dwg,.dxf"></div>
            <div class="field" style="margin-bottom:0;"><label>变更说明文件</label><input type="file" name="en_doc" accept=".doc,.docx,.xls,.xlsx,.pdf"></div>
          </div>
        </div>
        <p class="hint">法语 / 英语版本仅在该图纸需要外发时上传；内部使用的图纸只需上传中文版本，其余语言可以之后随时在版本记录里补充。</p>
        @error('zh_dwg')<p class="error-text">{{ $message }}</p>@enderror
        @error('zh_doc')<p class="error-text">{{ $message }}</p>@enderror
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('upload-modal').style.display='none'">取消</button>
        <button type="submit" class="btn btn-accent">发布变更</button>
      </div>
    </form>
  </div>
</div>

{{-- 重命名细分类 --}}
<div id="rename-modal" class="overlay" style="display:none;">
  <div class="modal">
    <div class="modal-head"><h3>重命名细分类</h3><button type="button" class="modal-close" onclick="document.getElementById('rename-modal').style.display='none'">&times;</button></div>
    <form method="POST" action="{{ route('subcategory.update', $subcategory) }}">
      @csrf @method('PATCH')
      <div class="modal-body">
        <div class="field"><label>细分类名称</label><input type="text" name="name" value="{{ $subcategory->name }}" required></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('rename-modal').style.display='none'">取消</button>
        <button type="submit" class="btn btn-accent">保存</button>
      </div>
    </form>
  </div>
</div>

{{-- 删除细分类（密码确认）--}}
<div id="delete-sub-modal" class="overlay" style="display:none;">
  <div class="modal">
    <div class="modal-head"><h3>删除细分类</h3><button type="button" class="modal-close" onclick="document.getElementById('delete-sub-modal').style.display='none'">&times;</button></div>
    <form method="POST" action="{{ route('subcategory.destroy', $subcategory) }}">
      @csrf @method('DELETE')
      <div class="modal-body">
        <p style="font-size:13.5px;color:var(--ink-soft);margin:0 0 14px;">确定删除「{{ $subcategory->name }}」这个细分类吗？其下有 {{ $versions->count() }} 条变更记录，删除后将无法恢复。</p>
        <div class="field">
          <label>请输入密码以确认删除</label>
          <input type="password" name="password" placeholder="密码" autocomplete="off" required>
        </div>
        @error('password')<p class="error-text">{{ $message }}</p>@enderror
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('delete-sub-modal').style.display='none'">取消</button>
        <button type="submit" class="btn" style="background:var(--danger);border-color:var(--danger);color:#fff;">确认删除</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection
