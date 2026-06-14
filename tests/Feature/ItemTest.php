<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\Item;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Mockery;

class ItemTest extends TestCase
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
     * Test Item CRUD operations.
     */
    public function test_item_crud_as_admin(): void
    {
        // Create vendor first
        $vendor = Vendor::create([
            'name' => 'Main Vendor',
            'code' => 'VND-MAIN',
            'contact_person' => 'John',
            'phone' => '12345',
            'address' => 'Street A',
        ]);

        // Create Item
        $response = $this->postJson('/api/items', [
            'code' => 'ITM-TEST-001',
            'name' => 'Kertas A4 HVS 80g',
            'description' => 'Kertas photocopy',
            'category' => 'ATK',
            'unit' => 'rim',
            'default_vendor_id' => $vendor->id,
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Kertas A4 HVS 80g')
                 ->assertJsonPath('data.code', 'ITM-TEST-001')
                 ->assertJsonPath('data.default_vendor.name', 'Main Vendor');

        $itemId = $response->json('data.id');

        // Get Detail
        $response = $this->getJson("/api/items/{$itemId}", ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Kertas A4 HVS 80g');

        // Update
        $response = $this->putJson("/api/items/{$itemId}", [
            'name' => 'Kertas A4 HVS 80g Super',
        ], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Kertas A4 HVS 80g Super');

        // Deactivate
        $response = $this->patchJson("/api/items/{$itemId}/deactivate", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.is_active', false);

        // Activate
        $response = $this->patchJson("/api/items/{$itemId}/activate", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.is_active', true);
    }

    /**
     * Test Item list filters and search.
     */
    public function test_item_list_filters(): void
    {
        Item::create([
            'code' => 'ITM-A',
            'name' => 'Item Alpha',
            'category' => 'ATK',
            'unit' => 'pcs',
            'is_active' => 1
        ]);

        Item::create([
            'code' => 'ITM-B',
            'name' => 'Item Beta',
            'category' => 'Elektronik',
            'unit' => 'unit',
            'is_active' => 0
        ]);

        // Category filter
        $response = $this->getJson('/api/items?category=ATK&search=Item Alpha', ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Item Alpha');

        // Active filter
        $response = $this->getJson('/api/items?is_active=0&search=Item Beta', ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Item Beta');

        // Search filter
        $response = $this->getJson('/api/items?search=Item Alpha', ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Item Alpha');
    }
}
