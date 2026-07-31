<?php

use App\Http\Middleware\EnsureProjectAccess;
use App\Http\Middleware\EnsureUserHasRole;
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

        // 生产环境部署在 Nginx（宝塔）后面，PHP-FPM 只监听本机 socket/端口，不直接对外，
        // 所以信任所有代理是安全的；如果以后加了额外的 CDN/负载均衡，把 '*' 换成具体网段
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
