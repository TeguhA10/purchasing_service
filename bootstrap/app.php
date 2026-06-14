<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuth::class,
            'jwt.superadmin' => \App\Http\Middleware\JwtSuperAdmin::class,
            'jwt.admin_purchasing' => \App\Http\Middleware\JwtAdminPurchasing::class,
            'jwt.admin_cabang' => \App\Http\Middleware\JwtAdminCabang::class,
            'jwt.staff_purchasing' => \App\Http\Middleware\JwtStaffPurchasing::class,
        ]);

        $middleware->encryptCookies(except: [
            'access_token',
            'refresh_token',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*'),
        );
    })->create();
