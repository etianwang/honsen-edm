<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // 页面里大量用了内联 style/onclick，CSP 只能放开 unsafe-inline；
        // Alpine.js 内部用 new Function() 求值指令表达式（x-data/@click 等），
        // 必须放开 unsafe-eval 否则整站 Alpine 交互全部失效；Alpine.js 本体走 unpkg CDN，
        // 字体走 Google Fonts，其余都限制在同源
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; ".
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com; ".
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ".
            "font-src 'self' https://fonts.gstatic.com data:; ".
            "img-src 'self' data:; ".
            "connect-src 'self'; ".
            "frame-ancestors 'self'; ".
            "base-uri 'self'; ".
            "form-action 'self';"
        );

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
