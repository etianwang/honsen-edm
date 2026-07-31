<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * 生产环境创建第一个超级管理员账号用的命令，避免依赖 DatabaseSeeder 里那几个固定弱密码的演示账号。
 * 演示账号（database/seeders/DatabaseSeeder.php）只应该在本地开发 / 内部测试环境使用，
 * 生产环境不要跑 `php artisan db:seed`。
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = '创建一个超级管理员账号（生产环境上线时用这个命令建第一个账号，而不是跑演示 seeder）';

    public function handle(): int
    {
        $name = $this->ask('姓名');
        $loginId = $this->ask('登录标识（工号 / 手机号）');
        $password = $this->secret('密码（至少 8 位，不会显示在屏幕上）');
        $confirm = $this->secret('再输入一遍密码确认');

        if ($password !== $confirm) {
            $this->error('两次输入的密码不一致，已取消创建');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'login_id' => $loginId, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:50'],
                'login_id' => ['required', 'string', 'max:50', 'unique:users,login_id'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'login_id' => $loginId,
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->info("已创建超级管理员账号「{$user->name}」（{$user->login_id}），请登录后自行完善其他账号和数据。");

        return self::SUCCESS;
    }
}
