<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_login_id_and_password(): void
    {
        $user = User::create([
            'name' => '陈工',
            'login_id' => 'D00001',
            'password' => Hash::make('honsen2026'),
            'role' => User::ROLE_DESIGNER,
        ]);

        $response = $this->post('/login', [
            'login_id' => 'D00001',
            'password' => 'honsen2026',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'name' => '陈工',
            'login_id' => 'D00001',
            'password' => Hash::make('honsen2026'),
            'role' => User::ROLE_DESIGNER,
        ]);

        $response = $this->post('/login', [
            'login_id' => 'D00001',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('login_id');
        $this->assertGuest();
    }

    public function test_a_disabled_account_cannot_log_in(): void
    {
        User::create([
            'name' => '陈工',
            'login_id' => 'D00001',
            'password' => Hash::make('honsen2026'),
            'role' => User::ROLE_DESIGNER,
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'login_id' => 'D00001',
            'password' => 'honsen2026',
        ]);

        $response->assertSessionHasErrors('login_id');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_login_is_rejected_without_a_valid_captcha_when_captcha_is_enabled(): void
    {
        config(['captcha.disable' => false]);

        User::create([
            'name' => '陈工',
            'login_id' => 'D00001',
            'password' => Hash::make('honsen2026'),
            'role' => User::ROLE_DESIGNER,
        ]);

        // 没有先生成过验证码，session 里没有待校验的验证码，任何输入都应该被拒绝
        $response = $this->post('/login', [
            'login_id' => 'D00001',
            'password' => 'honsen2026',
            'captcha' => 'whatever',
        ]);

        $response->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_repeated_failed_logins_get_rate_limited(): void
    {
        User::create([
            'name' => '陈工',
            'login_id' => 'D00001',
            'password' => Hash::make('honsen2026'),
            'role' => User::ROLE_DESIGNER,
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['login_id' => 'D00001', 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['login_id' => 'D00001', 'password' => 'wrong']);

        $response->assertStatus(429);
    }
}
