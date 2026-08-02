<div class="sidebar-backdrop" x-show="sidebarOpen" x-cloak @click="sidebarOpen=false"></div>
<div class="sidebar" :class="{open: sidebarOpen}">
  @foreach($tree as $team)
    <div class="team-block" x-data="{open:true}">
      <div class="team-header" :class="{open:open}">
        <button type="button" class="tree-toggle" @click="open=!open" aria-label="{{ __('展开或收起') }}">
          <span class="tree-toggle-icon" :class="{open:open}">&#9656;</span>
        </button>
        <svg class="tree-icon team-icon" width="13" height="13" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M1.5 3.5C1.5 2.94772 1.94772 2.5 2.5 2.5H6L7.5 4H13.5C14.0523 4 14.5 4.44772 14.5 5V12C14.5 12.5523 14.0523 13 13.5 13H2.5C1.94772 13 1.5 12.5523 1.5 12V3.5Z" fill="currentColor"/>
        </svg>
        <a href="{{ route('project.team', [$project, $team]) }}" class="t-name">{{ $team->name }}</a>
        <span class="t-count">{{ $team->specialties->count() }} {{ __('专业') }}</span>
      </div>
      <div class="spec-list" x-show="open">
        @foreach($team->specialties as $specialty)
          <div x-data="{open:true}">
            <div class="spec-row">
              <button type="button" class="tree-toggle" @click="open=!open" aria-label="{{ __('展开或收起') }}">
                <span class="tree-toggle-icon" :class="{open:open}">&#9656;</span>
              </button>
              <svg class="tree-icon spec-icon" width="11" height="11" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M1.5 3.5C1.5 2.94772 1.94772 2.5 2.5 2.5H6L7.5 4H13.5C14.0523 4 14.5 4.44772 14.5 5V12C14.5 12.5523 14.0523 13 13.5 13H2.5C1.94772 13 1.5 12.5523 1.5 12V3.5Z" fill="currentColor"/>
              </svg>
              <a href="{{ route('project.specialty', [$project, $specialty]) }}" class="s-name">{{ $specialty->name }}</a>
              <span class="s-count">{{ $specialty->subcategories->count() }}</span>
              @if($user->canManageContent())
                <button type="button" class="spec-add" title="{{ __('添加细分类') }}"
                  @click.stop="$dispatch('open-add-sub', {specialtyId: {{ $specialty->id }}, specialtyName: '{{ $specialty->name }}'})">+</button>
              @endif
            </div>
            <div class="sub-list" x-show="open">
              @foreach($specialty->subcategories as $sub)
                <a href="{{ route('subcategory.show', [$project, $sub]) }}" class="sub-row {{ (isset($subcategory) && $subcategory->id === $sub->id) ? 'selected' : '' }}">
                  <svg class="tree-icon sub-icon" width="11" height="11" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M4 1.5H9.5L12.5 4.5V14.5H4V1.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                    <path d="M9.5 1.5V4.5H12.5" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                  </svg>
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
    <div class="modal-head"><h3>{{ __('添加细分类') }}</h3><button type="button" class="modal-close" @click="show=false">&times;</button></div>
    <form method="POST" :action="'/projects/{{ $project->id }}/specialties/' + specialtyId + '/subcategories'">
      @csrf
      <div class="modal-body">
        <p class="hint" style="margin:0 0 12px;">{{ __('将在专业') }}「<span x-text="specialtyName"></span>」{{ __('下添加，仅对当前项目') }}「{{ $project->name }}」{{ __('生效。') }}</p>
        <div class="field">
          <label>{{ __('细分类名称') }}</label>
          <input type="text" name="name" placeholder="{{ __('例如：消防水') }}" required autofocus>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" @click="show=false">{{ __('取消') }}</button>
        <button type="submit" class="btn btn-accent">{{ __('添加') }}</button>
      </div>
    </form>
  </div>
</div>
@endif
