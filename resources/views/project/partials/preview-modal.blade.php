@php
  $langLabel = ['zh' => '中文', 'fr' => '法语', 'en' => '英语'];
  $availableLangs = collect(['zh', 'fr', 'en'])->intersect($version->availableLanguages())->values();
  $firstLang = $availableLangs->first();
@endphp
<div id="preview-modal-{{ $version->id }}" class="overlay" style="display:none;">
  <div class="modal wide">
    <div class="modal-head">
      <h3>图纸预览 · {{ $version->version_no }}</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('preview-modal-{{ $version->id }}').style.display='none'">&times;</button>
    </div>
    <div class="modal-body">
      @if($availableLangs->count() > 1)
        <div class="preview-tabs" x-data="{ lang: '{{ $firstLang }}' }">
          @foreach($availableLangs as $lang)
            <button type="button" class="ptab" :class="lang === '{{ $lang }}' ? 'active' : ''"
              @click="lang = '{{ $lang }}'; document.querySelectorAll('#preview-modal-{{ $version->id }} .preview-pane').forEach(el => el.style.display='none'); document.getElementById('preview-pane-{{ $version->id }}-{{ $lang }}').style.display='block';">
              {{ $langLabel[$lang] }}
            </button>
          @endforeach
        </div>
      @endif

      @foreach($availableLangs as $lang)
        @php
          $dwgList = $version->drawingsFor($lang, \App\Models\VersionDrawing::KIND_DWG);
          $pdfList = $version->drawingsFor($lang, \App\Models\VersionDrawing::KIND_PDF);
          $firstPdf = $pdfList->first();
        @endphp
        <div id="preview-pane-{{ $version->id }}-{{ $lang }}" class="preview-pane" style="display:{{ $lang === $firstLang ? 'block' : 'none' }};">
          @if($pdfList->count() > 1)
            <div class="chip-row" style="margin-bottom:10px;">
              @foreach($pdfList as $i => $d)
                <button type="button" class="chip {{ $i === 0 ? 'active' : '' }}"
                  onclick="document.querySelectorAll('#pdf-panes-{{ $version->id }}-{{ $lang }} .chip').forEach(c => c.classList.remove('active')); this.classList.add('active'); document.getElementById('pdf-frame-{{ $version->id }}-{{ $lang }}').src = {{ json_encode(route('version-drawing.preview', $d)) }};">{{ $d->original_name ?: basename($d->file_path) }}</button>
              @endforeach
            </div>
          @endif

          @if($firstPdf)
            <iframe id="pdf-frame-{{ $version->id }}-{{ $lang }}" src="{{ route('version-drawing.preview', $firstPdf) }}" style="width:100%;height:520px;border:1px solid var(--border);border-radius:9px;background:#fff;"></iframe>
          @else
            <div class="empty-state" style="padding:40px 20px;">
              <p>暂无图纸预览，可直接下载 DWG / 说明文件查看</p>
            </div>
          @endif

          <div class="file-chips" style="margin-top:10px;">
            @foreach($dwgList as $d)
              <a class="file-chip" href="{{ route('version-drawing.download', $d) }}"><span class="fname">{{ $d->original_name ?: basename($d->file_path) }}</span></a>
            @endforeach
            @foreach($pdfList as $d)
              <a class="file-chip" href="{{ route('version-drawing.download', $d) }}" target="_blank"><span class="fname">{{ $d->original_name ?: basename($d->file_path) }}</span></a>
            @endforeach
            @php($docFile = $version->fileFor($lang))
            @if($docFile?->doc_path)
              <a class="file-chip" href="{{ route('version-file.download', [$version, $lang]) }}"><span class="fname">{{ basename($docFile->doc_path) }}</span></a>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
