<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'fr' ? 'fr' : 'zh-CN' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('登录') }} · {{ __('深圳弘盛图纸管理系统') }}</title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#2A2520; --ink-soft:#4A4238; --muted:#7A7367;
    --paper:#E4E0D6; --card:#FBFAF6; --border:#D3CDBE;
    --navy:#3A2A18; --accent:#9C7A3D; --accent-hover:#84652F;
    --danger:#9C4632; --danger-bg:#EFDCD3;
    --font-display:'Archivo',sans-serif; --font-body:'IBM Plex Sans','PingFang SC',sans-serif;
  }
  *{box-sizing:border-box;}
  body{margin:0;min-height:100vh;display:flex;flex-direction:column;background:var(--paper);font-family:var(--font-body);color:var(--ink);}
  .login-wrap{flex:1;display:flex;align-items:center;justify-content:center;}
  .page-footer{padding:16px 20px;text-align:center;font-size:11px;color:var(--muted);}
  .page-footer a{color:var(--muted);text-decoration:none;}
  .page-footer a:hover{color:var(--accent);}
  .page-footer .footer-sep{margin:0 6px;}
  .login-card{width:380px;max-width:92vw;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:32px 30px;box-shadow:0 10px 28px rgba(20,24,28,.08);}
  .brand{display:flex;align-items:center;gap:10px;margin-bottom:22px;}
  .brand .name{font-family:var(--font-display);font-weight:700;font-size:17px;color:var(--navy);}
  .brand .sub{font-size:11.5px;color:var(--muted);margin-top:2px;}
  .field{margin-bottom:16px;}
  .field label{display:block;font-size:12.5px;font-weight:500;color:var(--ink-soft);margin-bottom:6px;}
  .field input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;}
  .field input:focus{outline:none;border-color:var(--accent);}
  .btn-login{width:100%;padding:11px;border-radius:8px;border:none;background:var(--accent);color:#fff;font-size:14px;font-weight:600;margin-top:6px;}
  .btn-login:hover{background:var(--accent-hover);}
  .error-box{background:var(--danger-bg);color:var(--danger);font-size:12.5px;padding:10px 12px;border-radius:8px;margin-bottom:16px;}
  .captcha-row{display:flex;gap:10px;align-items:stretch;}
  .captcha-row input{flex:1;min-width:0;}
  .captcha-row img{height:42px;width:130px;border-radius:8px;border:1px solid var(--border);cursor:pointer;flex:none;}
  .hint{font-size:11px;color:var(--muted);margin-top:5px;}
  .lang-switch{display:flex;justify-content:flex-end;gap:4px;font-size:11.5px;margin-bottom:12px;}
  .lang-switch a{text-decoration:none;color:var(--muted);}
  .lang-switch a.active{color:var(--ink-soft);font-weight:600;}
</style>
</head>
<body>
  <div class="login-wrap">
  <div class="login-card">
    <div class="lang-switch">
      <a href="{{ route('language.switch', 'zh_CN') }}" class="{{ app()->getLocale() === 'zh_CN' ? 'active' : '' }}">中文</a>
      <span>·</span>
      <a href="{{ route('language.switch', 'fr') }}" class="{{ app()->getLocale() === 'fr' ? 'active' : '' }}">Français</a>
    </div>
    <div class="brand">
      <svg width="32" height="32" viewBox="0 0 32 32">
        <rect width="32" height="32" rx="7" fill="#3A2A18"/>
        <path d="M6 23.5 L13.5 9.5 L17.5 16.5 L21 10.5 L26.5 23.5 Z" fill="#C79A4B"/>
        <circle cx="13.5" cy="9.5" r="2" fill="#F1EADB"/>
      </svg>
      <div>
        <div class="name">{{ __('深圳弘盛图纸管理系统') }}</div>
        <div class="sub">Honsen Africa · {{ __('工程图纸协同平台') }}</div>
      </div>
    </div>

    @if($errors->any())
      <div class="error-box">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}">
      @csrf
      <div class="field">
        <label>{{ __('工号 / 手机号') }}</label>
        <input type="text" name="login_id" value="{{ old('login_id') }}" autofocus required>
      </div>
      <div class="field">
        <label>{{ __('密码') }}</label>
        <input type="password" name="password" required>
      </div>
      @unless(config('captcha.disable'))
        <div class="field">
          <label>{{ __('验证码') }}</label>
          <div class="captcha-row">
            <input type="text" name="captcha" autocomplete="off" required>
            <img id="captcha-img" src="{{ captcha_src('honsen') }}" title="{{ __('看不清？点一下换一张') }}" onclick="this.src='{{ url('captcha/honsen') }}?'+Math.random()">
          </div>
          <p class="hint">{{ __('看不清就点一下图片换一张') }}</p>
        </div>
      @endunless
      <button type="submit" class="btn-login">{{ __('登录') }}</button>
    </form>
  </div>
  </div>
  <footer class="page-footer">
    <span>Honsen Africa</span>
    <span class="footer-sep">&middot;</span>
    <a href="https://github.com/etianwang" target="_blank" rel="noopener noreferrer">Design by Etienne</a>
  </footer>
</body>
</html>
