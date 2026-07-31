<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
            // 校验规则本身在 config('captcha.disable') 为 true 时永远通过，测试环境靠这个跳过验证码
            'captcha' => ['captcha'],
        ], [
            'captcha.captcha' => '验证码不正确，请重试',
        ]);

        $credentials = ['login_id' => $data['login_id'], 'password' => $data['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'login_id' => '工号 / 手机号或密码不正确',
            ])->onlyInput('login_id');
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['login_id' => '该账号已被禁用，请联系管理员']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
