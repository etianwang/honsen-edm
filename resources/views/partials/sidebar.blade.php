<div class="sidebar" x-data="{}">
  @foreach($tree as $team)
    <div class="team-block" x-data="{open:true}">
      <div class="team-header" :class="{open:open}" @click="open=!open">
        <span class="t-name">{{ $team->name }}</span>
        <span class="t-count">{{ $team->specialties->count() }} 专业</span>
      </div>
      <div class="spec-list" x-show="open">
        @foreach($team->specialties as $specialty)
          <div x-data="{open:true}">
            <div class="spec-row" @click="open=!open">
              <span class="s-name">{{ $specialty->name }}</span>
              <span class="s-count">{{ $specialty->subcategories->count() }}</span>
              @if($user->canManageContent())
                <button type="button" class="spec-add" title="添加细分类"
                  @click.stop="$dispatch('open-add-sub', {specialtyId: {{ $specialty->id }}, specialtyName: '{{ $specialty->name }}'})">+</button>
              @endif
            </div>
            <div class="sub-list" x-show="open">
              @foreach($specialty->subcategories as $sub)
                <a href="{{ route('subcategory.show', [$project, $sub]) }}" class="sub-row {{ (isset($subcategory) && $subcategory->id === $sub->id) ? 'selected' : '' }}">
                  <span class="sub-name">{{ $sub->name }}</span>
                  <span class="badge-count">{{ $sub->versions_count }}</span>
                </a>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endforeach
</div>

@if($user->canManageContent())
<div x-data="{show:false, specialtyId:null, specialtyName:''}"
     @open-add-sub.window="show=true; specialtyId=$event.detail.specialtyId; specialtyName=$event.detail.specialtyName"
     x-show="show" class="overlay" style="z-index:250;">
  <div class="modal" @click.outside="show=false">
    <div class="modal-head"><h3>添加细分类</h3><button type="button" class="modal-close" @click="show=false">&times;</button></div>
    <form method="POST" :action="'/projects/{{ $project->id }}/specialties/' + specialtyId + '/subcategories'">
      @csrf
      <div class="modal-body">
        <p class="hint" style="margin:0 0 12px;">将在专业「<span x-text="specialtyName"></span>」下添加，仅对当前项目「{{ $project->name }}」生效。</p>
        <div class="field">
          <label>细分类名称</label>
          <input type="text" name="name" placeholder="例如：消防水" required autofocus>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" @click="show=false">取消</button>
        <button type="submit" class="btn btn-accent">添加</button>
      </div>
    </form>
  </div>
</div>
@endif
