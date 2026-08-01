<?php

use App\Http\Middleware\EnsureProjectAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'project.access' => EnsureProjectAccess::class,
        ]);

        $middleware->web(append: [SetLocale::class]);

        // 生产环境部署在 Nginx（宝塔）后面，PHP-FPM 只监听本机 socket/端口，不直接对外，
        // 所以信任所有代理是安全的；如果以后加了额外的 CDN/负载均衡，把 '*' 换成具体网段
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // api/* 强制返回 JSON；此外前端异步上传等场景会显式带 Accept: application/json
        // 来请求 JSON 格式的校验错误（而不是被重定向回原页面看不到具体错误），也要认得。
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
