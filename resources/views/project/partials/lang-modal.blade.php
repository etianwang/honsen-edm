@php
  $dwgList = $version->drawingsFor($lang, \App\Models\VersionDrawing::KIND_DWG);
  $pdfList = $version->drawingsFor($lang, \App\Models\VersionDrawing::KIND_PDF);
  $docFile = $version->fileFor($lang);
  $formatSize = fn ($bytes) => $bytes ? ($bytes >= 1048576 ? round($bytes / 1048576, 1).'MB' : round($bytes / 1024).'KB') : '—';
@endphp
<div id="lang-modal-{{ $version->id }}-{{ $lang }}" class="overlay" style="display:none;">
  <div class="modal wide">
    <div class="modal-head">
      <h3>{{ $langLabel[$lang] }} {{ __('版本文件') }} · {{ $version->version_no }}</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('lang-modal-{{ $version->id }}-{{ $lang }}').style.display='none'">&times;</button>
    </div>
    <div class="modal-body">
      @if($dwgList->isNotEmpty() || $pdfList->isNotEmpty() || $docFile?->doc_path)
        <a class="file-chip" style="margin-bottom:14px;" href="{{ route('version.language-zip', [$version, $lang]) }}">📦 {{ __('打包下载本语言全部文件（ZIP）') }}</a>
      @endif

      <div class="lang-section-head">{{ __('DWG 原图') }}（{{ $dwgList->count() }} {{ __('份') }}）</div>
      @forelse($dwgList as $drawing)
        <div class="spec-admin-row" style="padding:8px 0;">
          <div class="spec-admin-head" style="margin-bottom:0;">
            <span class="sa-name" style="flex:none;font-family:var(--font-mono);font-size:12.5px;">{{ $drawing->original_name ?: basename($drawing->file_path) }}</span>
            <span style="font-size:11px;color:var(--muted);">{{ $formatSize($drawing->file_size) }} · {{ $drawing->created_at->format('Y-m-d') }} {{ __('上传') }}</span>
            <div class="spacer"></div>
            <a class="icon-btn" style="width:26px;height:26px;" title="{{ __('下载') }}" href="{{ route('version-drawing.download', $drawing) }}">↓</a>
            @if($user->canManageContent())
              <form method="POST" action="{{ route('version-drawing.destroy', $drawing) }}" onsubmit="return confirm('{{ __('确定删除') }}「{{ $drawing->original_name ?: basename($drawing->file_path) }}」？')">
                @csrf @method('DELETE')
                <button type="submit" class="icon-btn danger" style="width:26px;height:26px;" title="{{ __('删除') }}">✕</button>
              </form>
            @endif
          </div>
        </div>
      @empty
        <p class="hint" style="margin:0 0 10px;">{{ __('暂无 DWG 文件') }}</p>
      @endforelse

      @if($user->canManageContent())
        <form method="POST" action="{{ route('version-drawing.store', [$version, $lang, 'dwg']) }}" enctype="multipart/form-data" class="async-upload-form" data-upload-label="{{ __('追加') }} {{ $langLabel[$lang] }} DWG · {{ $version->version_no }}" style="margin:8px 0 18px;">
          @csrf
          <div class="field-row">
            <div class="field" style="margin-bottom:0;flex:1;"><input type="file" name="files[]" accept=".dwg,.dxf" multiple></div>
            <button type="submit" class="btn btn-sm">{{ __('追加 DWG') }}</button>
          </div>
        </form>
      @endif

      <div class="lang-section-head">{{ __('PDF 图纸') }}（{{ $pdfList->count() }} {{ __('份') }}）</div>
      @forelse($pdfList as $drawing)
        <div class="spec-admin-row" style="padding:8px 0;">
          <div class="spec-admin-head" style="margin-bottom:0;">
            <span class="sa-name" style="flex:none;font-family:var(--font-mono);font-size:12.5px;">{{ $drawing->original_name ?: basename($drawing->file_path) }}</span>
            <span style="font-size:11px;color:var(--muted);">{{ $formatSize($drawing->file_size) }} · {{ $drawing->created_at->format('Y-m-d') }} {{ __('上传') }}</span>
            <div class="spacer"></div>
            <a class="icon-btn" style="width:26px;height:26px;" title="{{ __('下载') }}" href="{{ route('version-drawing.download', $drawing) }}">↓</a>
            @if($user->canManageContent())
              <form method="POST" action="{{ route('version-drawing.destroy', $drawing) }}" onsubmit="return confirm('{{ __('确定删除') }}「{{ $drawing->original_name ?: basename($drawing->file_path) }}」？')">
                @csrf @method('DELETE')
                <button type="submit" class="icon-btn danger" style="width:26px;height:26px;" title="{{ __('删除') }}">✕</button>
              </form>
            @endif
          </div>
        </div>
      @empty
        <p class="hint" style="margin:0 0 10px;">{{ __('暂无 PDF 文件') }}</p>
      @endforelse

      @if($user->canManageContent())
        <form method="POST" action="{{ route('version-drawing.store', [$version, $lang, 'pdf']) }}" enctype="multipart/form-data" class="async-upload-form" data-upload-label="{{ __('追加') }} {{ $langLabel[$lang] }} PDF · {{ $version->version_no }}" style="margin:8px 0 18px;">
          @csrf
          <div class="field-row">
            <div class="field" style="margin-bottom:0;flex:1;"><input type="file" name="files[]" accept=".pdf" multiple></div>
            <button type="submit" class="btn btn-sm">{{ __('追加 PDF') }}</button>
          </div>
        </form>
      @endif

      <div class="lang-section-head">{{ __('变更说明文件') }}{{ $docFile?->doc_path ? '' : '（'.__('暂无').'）' }}</div>
      @if($docFile?->doc_path)
        <div class="spec-admin-row" style="padding:8px 0;">
          <div class="spec-admin-head" style="margin-bottom:0;">
            <span class="sa-name" style="flex:none;font-family:var(--font-mono);font-size:12.5px;">{{ basename($docFile->doc_path) }}</span>
            <span style="font-size:11px;color:var(--muted);">{{ $formatSize($docFile->doc_size) }}</span>
            <div class="spacer"></div>
            <a class="icon-btn" style="width:26px;height:26px;" title="{{ __('下载') }}" href="{{ route('version-file.download', [$version, $lang]) }}">↓</a>
            @if($user->canManageContent())
              <form method="POST" action="{{ route('version-file.destroy', [$version, $lang]) }}" onsubmit="return confirm('{{ __('确定移除说明文件？') }}')">
                @csrf @method('DELETE')
                <button type="submit" class="icon-btn danger" style="width:26px;height:26px;" title="{{ __('移除') }}">✕</button>
              </form>
            @endif
          </div>
        </div>
      @endif
      @if($user->canManageContent())
        <form method="POST" action="{{ route('version-file.store', [$version, $lang]) }}" enctype="multipart/form-data" class="async-upload-form" data-upload-label="{{ $docFile?->doc_path ? __('替换') : __('上传') }} {{ $langLabel[$lang] }} {{ __('说明文件') }} · {{ $version->version_no }}" style="margin:8px 0 0;">
          @csrf
          <div class="field-row">
            <div class="field" style="margin-bottom:0;flex:1;"><input type="file" name="doc" accept=".doc,.docx,.xls,.xlsx,.pdf,.txt,.dwg"></div>
            <button type="submit" class="btn btn-sm">{{ $docFile?->doc_path ? __('替换') : __('上传') }}</button>
          </div>
        </form>
      @endif

      @error('drawing')<p class="error-text" style="margin-top:12px;">{{ $message }}</p>@enderror
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('lang-modal-{{ $version->id }}-{{ $lang }}').style.display='none'">{{ __('关闭') }}</button>
    </div>
  </div>
</div>
