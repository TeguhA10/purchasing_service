<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuth
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('access_token');

        if (! $token) {
            $cookieHeader = $request->headers->get('cookie');
            if ($cookieHeader) {
                $cookies = [];
                parse_str(str_replace('; ', '&', $cookieHeader), $cookies);
                $token = $cookies['access_token'] ?? null;
            }
        }

        if (! $token) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = $this->authService->getMe();

        if (! $user || ! ($user->is_active ?? true)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Simpan user ke request
        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}