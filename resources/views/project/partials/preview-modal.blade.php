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
          $firstDwg = $dwgList->first();
        @endphp
        <div id="preview-pane-{{ $version->id }}-{{ $lang }}" class="preview-pane" style="display:{{ $lang === $firstLang ? 'block' : 'none' }};">
          @if($dwgList->count() > 1)
            <div class="chip-row" style="margin-bottom:10px;">
              @foreach($dwgList as $i => $d)
                <button type="button" class="chip {{ $i === 0 ? 'active' : '' }}"
                  onclick="document.querySelectorAll('#dwg-panes-{{ $version->id }}-{{ $lang }} .chip').forEach(c => c.classList.remove('active')); this.classList.add('active'); document.querySelectorAll('#dwg-panes-{{ $version->id }}-{{ $lang }} .dwg-pane').forEach(p => p.style.display='none'); document.getElementById('dwg-pane-{{ $d->id }}').style.display='block'; honsenLoadDxfPreview({{ $d->id }}, {{ json_encode($d->hasInteractivePreview()) }}, {{ json_encode($d->hasInteractivePreview() ? route('version-drawing.dxf', $d) : null) }})">{{ $d->original_name ?: basename($d->file_path) }}</button>
              @endforeach
            </div>
          @endif

          <div id="dwg-panes-{{ $version->id }}-{{ $lang }}">
            @forelse($dwgList as $i => $d)
              <div id="dwg-pane-{{ $d->id }}" class="dwg-pane" style="display:{{ $i === 0 ? 'block' : 'none' }};">
                @if($d->hasInteractivePreview())
                  <div id="dxf-container-{{ $d->id }}" style="width:100%;height:460px;background:var(--navy);border-radius:9px;position:relative;">
                    <div class="dxf-status" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--navy-text-dim);font-size:12.5px;">正在加载图纸…</div>
                  </div>
                  <p class="hint" style="margin-top:8px;">鼠标滚轮缩放，拖拽平移。这是自动从 DWG 转换出的预览，如需精确编辑请下载原始 DWG 文件。</p>
                  @if($i === 0)
                    <script>honsenLoadDxfPreview({{ $d->id }}, true, {{ json_encode(route('version-drawing.dxf', $d)) }});</script>
                  @endif
                @elseif($d->isDxfConverting())
                  <div class="empty-state" style="padding:40px 20px;">
                    <p>图纸正在后台转换为可交互预览格式，一般 1～2 分钟内完成，请稍后刷新本页面查看</p>
                    <p class="hint" style="margin-top:6px;">转换期间不影响下载原始 DWG 文件</p>
                  </div>
                @else
                  <div class="empty-state" style="padding:40px 20px;">
                    <p>该文件暂无可交互预览，可直接下载查看</p>
                  </div>
                @endif
              </div>
            @empty
              <div class="empty-state" style="padding:40px 20px;">
                <p>该语言版本暂无可交互预览，可直接下载查看</p>
              </div>
            @endforelse
          </div>

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

@once
<script type="module">
  import { DxfViewer } from "https://cdn.jsdelivr.net/npm/dxf-viewer/+esm";

  window.__honsenDxfViewers = window.__honsenDxfViewers || {};

  window.honsenLoadDxfPreview = function (key, hasPreview, dxfUrl) {
    if (!hasPreview) return;
    if (window.__honsenDxfViewers[key]) return; // 已加载过，不重复初始化

    const container = document.getElementById('dxf-container-' + key);
    if (!container) return;

    const viewer = new DxfViewer(container, { autoResize: true });
    window.__honsenDxfViewers[key] = viewer;

    viewer.Load({ url: dxfUrl }).then(() => {
      const status = container.querySelector('.dxf-status');
      if (status) status.remove();
    }).catch((err) => {
      console.error('DXF 预览加载失败', err);
      const status = container.querySelector('.dxf-status');
      if (status) status.textContent = '预览加载失败，请直接下载图纸查看';
    });
  };
</script>
@endonce
