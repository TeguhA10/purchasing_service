<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthService
{
    protected string $baseUrl;
    protected ?string $token = null;

    // Request-level cache to prevent duplicate HTTP calls
    protected static ?object $cachedMe = null;
    protected static array $cachedUsers = [];

    public function __construct()
    {
        $this->baseUrl = config('services.auth.url', 'http://localhost:8000');
        $this->token = request()->cookie('access_token');

        if (!$this->token) {
            $cookieHeader = request()->headers->get('cookie');
            if ($cookieHeader) {
                $cookies = [];
                parse_str(str_replace('; ', '&', $cookieHeader), $cookies);
                $this->token = $cookies['access_token'] ?? null;
            }
        }
    }

    /**
     * Get the logged-in user info from auth-service.
     * Uses the access token from request cookies.
     */
    public function getMe(): ?object
    {
        if (self::$cachedMe !== null && !app()->runningUnitTests()) {
            return self::$cachedMe;
        }

        if (!$this->token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Cookie' => 'access_token=' . $this->token,
                'Accept' => 'application/json',
            ])->get("{$this->baseUrl}/api/me");

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['data'])) {
                    self::$cachedMe = (object) $body['data'];
                    return self::$cachedMe;
                }
            }
        } catch (\Throwable $e) {
            Log::error('AuthService getMe failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get a specific user by ID from auth-service.
     * Uses the access token from request cookies to authorize.
     */
    public function getUserById(int $userId): ?object
    {
        if (isset(self::$cachedUsers[$userId]) && !app()->runningUnitTests()) {
            return self::$cachedUsers[$userId];
        }

        if (!$this->token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Cookie' => 'access_token=' . $this->token,
                'Accept' => 'application/json',
            ])->get("{$this->baseUrl}/api/users/{$userId}");

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['data'])) {
                    self::$cachedUsers[$userId] = (object) $body['data'];
                    return self::$cachedUsers[$userId];
                }
            }
        } catch (\Throwable $e) {
            Log::error("AuthService getUserById ($userId) failed: " . $e->getMessage());
        }

        return null;
    }
}
