@extends('layouts.app')
@section('title', __('个人设置'))
@section('content')
<div class="admin-shell">
  <div class="admin-inner" style="max-width:520px;">
    <h1 class="page-title">{{ __('个人设置') }}</h1>
    <p class="page-sub">{{ __('修改自己的姓名和登录密码') }}</p>

    @if(session('toast'))<div class="admin-note" style="background:var(--success-bg);color:var(--success);">{{ session('toast') }}</div>@endif
    @if($errors->any())<div class="admin-note" style="background:var(--danger-bg);color:var(--danger);">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('profile.update') }}" style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;">
      @csrf
      @method('PATCH')
      <div class="field">
        <label>{{ __('登录标识（工号 / 手机号，不可修改）') }}</label>
        <input type="text" value="{{ $user->login_id }}" disabled style="background:var(--paper);color:var(--muted);">
      </div>
      <div class="field">
        <label>{{ __('姓名') }}</label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
      </div>
      <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
      <p class="hint" style="margin:0 0 12px;">{{ __('如需修改密码，填写下面三项；不改密码可以留空。') }}</p>
      <div class="field">
        <label>{{ __('当前密码') }}</label>
        <input type="password" name="current_password" autocomplete="off">
      </div>
      <div class="field-row">
        <div class="field">
          <label>{{ __('新密码') }}</label>
          <input type="password" name="new_password" autocomplete="off" minlength="8">
        </div>
        <div class="field">
          <label>{{ __('确认新密码') }}</label>
          <input type="password" name="new_password_confirmation" autocomplete="off" minlength="8">
        </div>
      </div>
      <button type="submit" class="btn btn-accent" style="width:100%;justify-content:center;margin-top:8px;">{{ __('保存') }}</button>
    </form>
  </div>
</div>
@endsection
