<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\AuthService;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Mockery;

class PurchaseOrderTest extends TestCase
{
    use DatabaseTransactions;

    protected $mockAuthService;
    protected $mockEmployeeService;
    protected $activeVendor;
    protected $activeItem;
    protected $currentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAuthService = Mockery::mock(AuthService::class);
        $this->app->instance(AuthService::class, $this->mockAuthService);

        $this->mockEmployeeService = Mockery::mock(EmployeeService::class);
        $this->app->instance(EmployeeService::class, $this->mockEmployeeService);

        // Seed basic database elements
        $this->activeVendor = Vendor::create([
            'name' => 'Active Vendor Ltd',
            'code' => 'VND-ACT',
            'contact_person' => 'Supplier John',
            'phone' => '08111222333',
            'address' => 'Supplier Ave 10',
            'is_active' => 1
        ]);

        $this->activeItem = Item::create([
            'code' => 'ITM-ACT-01',
            'name' => 'Photocopy Paper',
            'category' => 'ATK',
            'unit' => 'rim',
            'default_vendor_id' => $this->activeVendor->id,
            'is_active' => 1
        ]);

        // Default mock responses
        $this->currentUser = (object)[
            'id' => 10,
            'name' => 'Purchasing Staff',
            'role' => 'staff_purchasing',
            'branch_id' => 3,
            'is_active' => true,
        ];

        $this->mockAuthService->shouldReceive('getMe')->andReturnUsing(function () {
            return $this->currentUser;
        });

        $this->mockEmployeeService->shouldReceive('getBranchByID')->with(3)->andReturn((object)[
            'id' => 3,
            'name' => 'Cabang Bandung',
            'code' => 'BDG',
            'is_active' => true
        ])->byDefault();
    }

    /**
     * Test PO creation in draft state.
     */
    public function test_po_creation_in_draft_state(): void
    {
        $response = $this->postJson('/api/purchase-orders', [
            'vendor_id' => $this->activeVendor->id,
            'tanggal_po' => '2026-06-15',
            'tanggal_dibutuhkan' => '2026-06-20',
            'catatan' => 'Urgent order',
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', 'draft')
                 ->assertJsonPath('data.branch_name', 'Cabang Bandung')
                 ->assertJsonPath('data.branch_code', 'BDG')
                 ->assertJsonPath('data.requested_by', 10);

        $poNumber = $response->json('data.po_number');
        $this->assertStringStartsWith('PO/BDG/2026/', $poNumber);
    }

    /**
     * Test PO creation by admin with arbitrary branch.
     */
    public function test_po_creation_as_admin_with_arbitrary_branch(): void
    {
        // Set user as admin_purchasing
        $this->currentUser = (object)[
            'id' => 1,
            'name' => 'Admin Purchasing',
            'role' => 'admin_purchasing',
            'branch_id' => null,
            'is_active' => true,
        ];

        // Mock branch ID 5
        $this->mockEmployeeService->shouldReceive('getBranchByID')->with(5)->andReturn((object)[
            'id' => 5,
            'name' => 'Cabang Jakarta',
            'code' => 'JKT',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/purchase-orders', [
            'branch_id' => 5,
            'vendor_id' => $this->activeVendor->id,
            'tanggal_po' => '2026-06-15',
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(201)
                 ->assertJsonPath('data.branch_name', 'Cabang Jakarta')
                 ->assertJsonPath('data.branch_code', 'JKT');
    }

    /**
     * Test PO items manipulation.
     */
    public function test_po_items_update_and_total_triggers(): void
    {
        // 1. Create PO
        $po = PurchaseOrder::create([
            'po_number' => 'PO/BDG/2026/0001',
            'branch_id' => 3,
            'branch_name' => 'Cabang Bandung',
            'branch_code' => 'BDG',
            'vendor_id' => $this->activeVendor->id,
            'requested_by' => 10,
            'status' => 'draft',
            'tanggal_po' => '2026-06-15',
        ]);

        // 2. Put items
        $response = $this->putJson("/api/purchase-orders/{$po->id}/items", [
            'items' => [
                [
                    'item_id' => $this->activeItem->id,
                    'quantity' => 5,
                    'unit_price' => 15000,
                    'notes' => 'Tebal 80gr',
                ]
            ]
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(200)
                 ->assertJsonPath('data.items.0.item_name', 'Photocopy Paper')
                 ->assertJsonPath('data.items.0.subtotal', '75000.00')
                 ->assertJsonPath('data.total_amount', '75000.00');

        // Check if database trigger automatically computed the subtotal & total
        $poFresh = PurchaseOrder::find($po->id);
        $this->assertEquals(75000, $poFresh->total_amount);
    }

    /**
     * Test PO status workflow state machine.
     */
    public function test_po_status_workflow_transitions(): void
    {
        // Create PO
        $po = PurchaseOrder::create([
            'po_number' => 'PO/BDG/2026/0002',
            'branch_id' => 3,
            'branch_name' => 'Cabang Bandung',
            'branch_code' => 'BDG',
            'vendor_id' => $this->activeVendor->id,
            'requested_by' => 10,
            'status' => 'draft',
            'tanggal_po' => '2026-06-15',
        ]);

        // Add items to calculate amount
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $this->activeItem->id,
            'item_name' => 'Photocopy Paper',
            'quantity' => 10,
            'unit' => 'rim',
            'unit_price' => 12000,
            'subtotal' => 120000,
        ]);

        // 1. Submit
        $response = $this->patchJson("/api/purchase-orders/{$po->id}/submit", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)->assertJsonPath('data.status', 'submitted');

        // Verify that trying to modify items in submitted status fails
        $response = $this->putJson("/api/purchase-orders/{$po->id}/items", [
            'items' => [['item_id' => $this->activeItem->id, 'quantity' => 2, 'unit_price' => 12000]]
        ], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(422);

        // 2. Reject (requires role admin_purchasing/superadmin)
        $this->currentUser = (object)[
            'id' => 1,
            'name' => 'Admin Purchasing',
            'role' => 'admin_purchasing',
            'branch_id' => null,
            'is_active' => true,
        ];

        $response = $this->patchJson("/api/purchase-orders/{$po->id}/reject", [
            'rejection_reason' => 'Too expensive'
        ], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'rejected')
                 ->assertJsonPath('data.rejection_reason', 'Too expensive')
                 ->assertJsonPath('data.approved_by', 1);

        // Reset to submitted for testing Approval
        $po->status = 'submitted';
        $po->save();

        // 3. Approve
        $response = $this->patchJson("/api/purchase-orders/{$po->id}/approve", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'approved');

        // 4. Receive (requires admin_cabang or superadmin)
        $this->currentUser = (object)[
            'id' => 5,
            'name' => 'Cabang Jakarta Admin',
            'role' => 'admin_cabang',
            'branch_id' => 3, // Bandung branch matches PO branch
            'is_active' => true,
        ];

        $response = $this->patchJson("/api/purchase-orders/{$po->id}/receive", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'received');

        // Verify that database trigger updated the item last_price automatically
        $itemFresh = Item::find($this->activeItem->id);
        $this->assertEquals(12000.00, $itemFresh->last_price);
    }

    /**
     * Test that unauthorized roles are forbidden from approving, rejecting, or receiving a PO.
     */
    public function test_po_transitions_forbidden_for_invalid_roles(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO/BDG/2026/0003',
            'branch_id' => 3,
            'branch_name' => 'Cabang Bandung',
            'branch_code' => 'BDG',
            'vendor_id' => $this->activeVendor->id,
            'requested_by' => 10,
            'status' => 'submitted',
            'tanggal_po' => '2026-06-15',
        ]);

        // Staff tries to approve (restricted to admin_purchasing/superadmin)
        $this->currentUser = (object)[
            'id' => 10,
            'name' => 'Purchasing Staff',
            'role' => 'staff_purchasing',
            'branch_id' => 3,
            'is_active' => true,
        ];
        $response = $this->patchJson("/api/purchase-orders/{$po->id}/approve", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(403);

        // Staff tries to reject (restricted to admin_purchasing/superadmin)
        $response = $this->patchJson("/api/purchase-orders/{$po->id}/reject", ['rejection_reason' => 'No'], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(403);

        // Staff tries to receive (restricted to admin_cabang/superadmin)
        $po->status = 'approved';
        $po->save();
        $response = $this->patchJson("/api/purchase-orders/{$po->id}/receive", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(403);

        // Admin Purchasing tries to receive (restricted to admin_cabang/superadmin)
        $this->currentUser = (object)[
            'id' => 1,
            'name' => 'Admin Purchasing',
            'role' => 'admin_purchasing',
            'branch_id' => null,
            'is_active' => true,
        ];
        $response = $this->patchJson("/api/purchase-orders/{$po->id}/receive", [], ['Cookie' => 'access_token=valid-token']);
        $response->assertStatus(403);
    }
}
