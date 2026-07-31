<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem as Flysystem;
use Overtrue\Flysystem\Cos\CosAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 团队 / 专业为全公司统一标准分类，管理员 / 超级管理员可增删改
        Gate::define('manage-taxonomy', fn (User $user) => $user->isAdminTier());

        // 账号管理入口：管理员 / 超级管理员都能进；能否分配管理员级角色见 User::canAssignRole()
        Gate::define('manage-users', fn (User $user) => $user->isAdminTier());

        // 注册腾讯云 COS 磁盘驱动，切换 FILESYSTEM_DISK=cos 即可生效
        Storage::extend('cos', function ($app, array $config) {
            $adapter = new CosAdapter($config);

            return new LaravelFilesystemAdapter(new Flysystem($adapter), $adapter, $config);
        });

        // 当前登录用户在所有视图（包括 @extends 的子模板）中都可用，
        // 因为子模板的 @section 会先于父布局的 @php 求值，不能只在布局里赋值
        View::composer('*', function ($view) {
            $view->with('user', Auth::user());
        });

        // 未读通知数只在顶部布局里显示一次，不需要在每个子模板都查一遍数据库
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            $view->with('unreadNotificationCount', $user ? $user->unreadNotifications()->count() : 0);
        });
    }
}
