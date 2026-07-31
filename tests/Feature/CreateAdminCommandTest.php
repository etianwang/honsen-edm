<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin_account(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('姓名', '超级管理员')
            ->expectsQuestion('登录标识（工号 / 手机号）', 'S00001')
            ->expectsQuestion('密码（至少 8 位，不会显示在屏幕上）', 'a-strong-password')
            ->expectsQuestion('再输入一遍密码确认', 'a-strong-password')
            ->assertExitCode(0);

        $user = User::where('login_id', 'S00001')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $user->role);
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
    }

    public function test_it_fails_when_passwords_do_not_match(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('姓名', '超级管理员')
            ->expectsQuestion('登录标识（工号 / 手机号）', 'S00002')
            ->expectsQuestion('密码（至少 8 位，不会显示在屏幕上）', 'password-one')
            ->expectsQuestion('再输入一遍密码确认', 'password-two')
            ->assertExitCode(1);

        $this->assertNull(User::where('login_id', 'S00002')->first());
    }

    public function test_it_rejects_a_duplicate_login_id(): void
    {
        User::create([
            'name' => '已存在',
            'login_id' => 'DUP001',
            'password' => Hash::make('whatever123'),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->artisan('app:create-admin')
            ->expectsQuestion('姓名', '新账号')
            ->expectsQuestion('登录标识（工号 / 手机号）', 'DUP001')
            ->expectsQuestion('密码（至少 8 位，不会显示在屏幕上）', 'a-strong-password')
            ->expectsQuestion('再输入一遍密码确认', 'a-strong-password')
            ->assertExitCode(1);
    }
}
