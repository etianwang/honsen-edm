@php
  $langLabel = ['zh' => '中文', 'fr' => '法语', 'en' => '英语'];
  $availableLangs = collect(['zh', 'fr', 'en'])->filter(fn ($l) => $version->fileFor($l));
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
              @click="lang = '{{ $lang }}'; document.querySelectorAll('#preview-modal-{{ $version->id }} .preview-pane').forEach(el => el.style.display='none'); document.getElementById('preview-pane-{{ $version->id }}-{{ $lang }}').style.display='block'; honsenLoadDxfPreview('{{ $version->id }}', '{{ $lang }}', {{ json_encode($version->fileFor($lang)?->hasInteractivePreview() ?? false) }}, {{ json_encode($version->fileFor($lang) ? route('version-file.dxf', [$version, $lang]) : null) }})">
              {{ $langLabel[$lang] }}
            </button>
          @endforeach
        </div>
      @endif

      @foreach($availableLangs as $lang)
        @php($file = $version->fileFor($lang))
        <div id="preview-pane-{{ $version->id }}-{{ $lang }}" class="preview-pane" style="display:{{ $lang === $firstLang ? 'block' : 'none' }};">
          @if($file->hasInteractivePreview())
            <div id="dxf-container-{{ $version->id }}-{{ $lang }}" style="width:100%;height:460px;background:var(--navy);border-radius:9px;position:relative;">
              <div class="dxf-status" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--navy-text-dim);font-size:12.5px;">正在加载图纸…</div>
            </div>
            <p class="hint" style="margin-top:8px;">鼠标滚轮缩放，拖拽平移。这是自动从 DWG 转换出的预览，如需精确编辑请下载原始 DWG 文件。</p>
          @elseif($file->isDxfConverting())
            <div class="empty-state" style="padding:40px 20px;">
              <p>图纸正在后台转换为可交互预览格式，一般 1～2 分钟内完成，请稍后刷新本页面查看</p>
              <p class="hint" style="margin-top:6px;">转换期间不影响下载原始 DWG 文件</p>
            </div>
          @else
            <div class="empty-state" style="padding:40px 20px;">
              <p>该语言版本暂无可交互预览{{ $file->dwg_path ? '（转换失败或当前环境不支持）' : '' }}，可直接下载查看</p>
            </div>
          @endif
          <div class="file-chips" style="margin-top:10px;">
            @if($file->dwg_path)
              <a class="file-chip" href="{{ route('version-file.download', [$version, $lang, 'dwg']) }}">图纸下载（DWG）</a>
            @endif
            @if($file->doc_path)
              <a class="file-chip" href="{{ route('version-file.download', [$version, $lang, 'doc']) }}">说明文档下载</a>
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

  window.honsenLoadDxfPreview = function (versionId, lang, hasPreview, dxfUrl) {
    if (!hasPreview) return;
    const key = versionId + '-' + lang;
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
