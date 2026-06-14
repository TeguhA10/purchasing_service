<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\PurchaseOrder;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Mockery;

class VendorTest extends TestCase
{
    use DatabaseTransactions;

    protected $mockAuthService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAuthService = Mockery::mock(AuthService::class);
        $this->app->instance(AuthService::class, $this->mockAuthService);

        // Default mock user
        $this->mockAuthService->shouldReceive('getMe')->andReturn((object)[
            'id' => 1,
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'role' => 'superadmin',
            'branch_id' => null,
            'is_active' => true,
        ])->byDefault();
    }

    /**
     * Test Vendor CRUD by Admin/Superadmin.
     */
    public function test_vendor_crud_as_admin(): void
    {
        // Create Vendor
        $response = $this->postJson('/api/vendors', [
            'name' => 'Indo Jaya Perkasa',
            'code' => 'VND-TEST-001',
            'contact_person' => 'Budi Santoso',
            'phone' => '08123456789',
            'email' => 'budi@indojaya.com',
            'address' => 'Jl. Industri No. 12, Bandung',
            'npwp' => '12.345.678.9-123.000',
            'payment_term_days' => 30,
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Indo Jaya Perkasa')
                 ->assertJsonPath('data.code', 'VND-TEST-001');

        $vendorId = $response->json('data.id');

        // Get Detail
        $response = $this->getJson("/api/vendors/{$vendorId}", ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Indo Jaya Perkasa');

        // Update
        $response = $this->putJson("/api/vendors/{$vendorId}", [
            'name' => 'Indo Jaya Perkasa Baru',
        ], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Indo Jaya Perkasa Baru');

        // Deactivate
        $response = $this->patchJson("/api/vendors/{$vendorId}/deactivate", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.is_active', false);

        // Activate
        $response = $this->patchJson("/api/vendors/{$vendorId}/activate", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.is_active', true);
    }

    /**
     * Test Vendor creation forbidden for staff role.
     */
    public function test_vendor_creation_forbidden_for_staff(): void
    {
        // Mock role as staff_purchasing
        $this->mockAuthService->shouldReceive('getMe')->andReturn((object)[
            'id' => 2,
            'name' => 'Purchasing Staff',
            'email' => 'staff@example.com',
            'role' => 'staff_purchasing',
            'branch_id' => 3,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/vendors', [
            'name' => 'Indo Jaya Perkasa',
            'code' => 'VND-TEST-002',
            'contact_person' => 'Budi Santoso',
            'phone' => '08123456789',
            'address' => 'Jl. Industri No. 12, Bandung',
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(403);
    }

    /**
     * Test listing with search, filter, and pagination.
     */
    public function test_vendor_list_filters_and_pagination(): void
    {
        // Seed vendors
        Vendor::create([
            'name' => 'Vendor Alpha',
            'code' => 'VND-ALP',
            'contact_person' => 'Alpha Man',
            'phone' => '111',
            'address' => 'Street A',
            'is_active' => 1
        ]);

        Vendor::create([
            'name' => 'Vendor Beta',
            'code' => 'VND-BET',
            'contact_person' => 'Beta Man',
            'phone' => '222',
            'address' => 'Street B',
            'is_active' => 0
        ]);

        // List active only
        $response = $this->getJson('/api/vendors?is_active=1&search=Vendor Alpha', ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Vendor Alpha');

        // List search
        $response = $this->getJson('/api/vendors?search=Vendor Beta', ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Vendor Beta');

        // Check meta
        $response->assertJsonStructure([
            'data',
            'meta' => ['page', 'limit', 'total', 'total_pages']
        ]);
    }
}
