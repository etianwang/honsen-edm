@php
  $existing = $version->fileFor($lang);
  $isReplace = (bool) $existing;
@endphp
<div id="lang-modal-{{ $version->id }}-{{ $lang }}" class="overlay" style="display:none;">
  <div class="modal">
    <div class="modal-head">
      <h3>{{ $isReplace ? '替换' : '补充' }}{{ $langLabel[$lang] }}版本 · {{ $version->version_no }}</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('lang-modal-{{ $version->id }}-{{ $lang }}').style.display='none'">&times;</button>
    </div>
    <form method="POST" action="{{ route('version-file.store', [$version, $lang]) }}" enctype="multipart/form-data">
      @csrf
      <div class="modal-body">
        <p class="hint" style="margin:0 0 14px;">
          @if($isReplace)
            重新上传该版本的{{ $langLabel[$lang] }}文件，新文件会覆盖当前版本。只选择要更换的文件即可，未选择的保持不变。
          @else
            为该版本补充{{ $langLabel[$lang] }}图纸，用于图纸外发。
          @endif
        </p>
        <div class="field">
          <label>图纸文件（DWG）</label>
          <input type="file" name="dwg" accept=".dwg,.dxf">
          @if($isReplace && $existing->dwg_path)<p class="hint">当前：{{ basename($existing->dwg_path) }}</p>@endif
        </div>
        <div class="field">
          <label>变更说明文件</label>
          <input type="file" name="doc" accept=".doc,.docx,.xls,.xlsx,.pdf">
          @if($isReplace && $existing->doc_path)<p class="hint">当前：{{ basename($existing->doc_path) }}</p>@endif
        </div>
      </div>
      <div class="modal-foot" style="justify-content:space-between;">
        <div>
          @if($isReplace && $lang !== 'zh' && $user->canManageContent())
            <button type="submit" form="lang-remove-form-{{ $version->id }}-{{ $lang }}" class="btn btn-sm" style="color:var(--danger);border-color:var(--danger-bg);">移除此语言</button>
          @endif
        </div>
        <div style="display:flex;gap:8px;">
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('lang-modal-{{ $version->id }}-{{ $lang }}').style.display='none'">取消</button>
          <button type="submit" class="btn btn-accent">保存</button>
        </div>
      </div>
    </form>
    @if($isReplace && $lang !== 'zh' && $user->canManageContent())
      <form id="lang-remove-form-{{ $version->id }}-{{ $lang }}" method="POST" action="{{ route('version-file.destroy', [$version, $lang]) }}" onsubmit="return confirm('确定移除{{ $langLabel[$lang] }}版本？')" style="display:none;">
        @csrf @method('DELETE')
      </form>
    @endif
  </div>
</div>
