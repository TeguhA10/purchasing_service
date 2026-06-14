<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmployeeService
{
    protected string $baseUrl;
    protected ?string $token = null;

    public function __construct()
    {
        $this->baseUrl = config('services.employee.url', 'http://localhost:8001');
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
     * Get list of active branches.
     */
    public function getBranches(): array
    {
        try {
            $response = Http::acceptJson()->get("{$this->baseUrl}/api/branches");

            if ($response->successful()) {
                $body = $response->json();
                return $body['data'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::error('EmployeeService getBranches failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get branch detail by ID.
     */
    public function getBranchByID(int $branchID): ?object
    {
        try {
            $request = Http::acceptJson();
            if ($this->token) {
                $request = $request->withHeaders([
                    'Cookie' => 'access_token=' . $this->token,
                ]);
            }

            $response = $request->get("{$this->baseUrl}/api/branches/{$branchID}");

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['data'])) {
                    return (object) $body['data'];
                }
            }
        } catch (\Throwable $e) {
            Log::error("EmployeeService getBranchByID ($branchID) failed: " . $e->getMessage());
        }

        return null;
    }
}
