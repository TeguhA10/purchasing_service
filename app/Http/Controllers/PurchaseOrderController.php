<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Vendor;
use App\Models\Item;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {
    }

    /**
     * Helper to validate branch and role constraints.
     */
    protected function checkBranchAccess(Request $request, int $branchId): void
    {
        $user = $request->attributes->get('auth_user');
        if (in_array($user->role, ['staff_purchasing', 'admin_cabang'])) {
            if ($user->branch_id !== $branchId) {
                abort(response()->json(['message' => 'Forbidden. You do not have access to this branch.'], 403));
            }
        }
    }

    /**
     * Helper to check access for a specific Purchase Order.
     */
    protected function checkPOAccess(Request $request, PurchaseOrder $po): void
    {
        $user = $request->attributes->get('auth_user');
        if (in_array($user->role, ['staff_purchasing', 'admin_cabang'])) {
            if ($po->branch_id !== $user->branch_id) {
                abort(response()->json(['message' => 'Forbidden. You do not have access to this purchase order.'], 403));
            }
        }
    }

    /**
     * GET /purchase-orders
     * List all purchase orders with filters, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $query = PurchaseOrder::with(['vendor']);

        // RBAC: staff_purchasing & admin_cabang can only see POs of their own branch
        if (in_array($user->role, ['staff_purchasing', 'admin_cabang'])) {
            $query->where('branch_id', $user->branch_id);
        } else {
            // Admin/Superadmin can filter by branch
            if ($request->has('branch_id') && !empty($request->branch_id)) {
                $query->where('branch_id', $request->branch_id);
            }
        }

        // Filters
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('vendor_id') && !empty($request->vendor_id)) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->where('tanggal_po', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->where('tanggal_po', '<=', $request->end_date);
        }

        // Pagination
        $limit = (int) $request->query('limit', 10);
        $page = (int) $request->query('page', 1);
        $total = $query->count();
        $totalPages = (int) ceil($total / $limit);

        $pos = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Success',
            'data' => $pos,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ]
        ]);
    }

    /**
     * GET /purchase-orders/{id}
     * Get PO details including items.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::with(['vendor', 'items.item'])->find($id);

        if (!$po) {
            return response()->json(['message' => 'Purchase Order not found.'], 404);
        }

        $this->checkPOAccess($request, $po);

        return response()->json([
            'message' => 'Success',
            'data' => $po
        ]);
    }

    /**
     * POST /purchase-orders
     * Create a new purchase order (draft).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        // Validation payload
        $validated = $request->validate([
            'branch_id' => [
                in_array($user->role, ['staff_purchasing', 'admin_cabang']) ? 'nullable' : 'required',
                'integer'
            ],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'tanggal_po' => ['required', 'date'],
            'tanggal_dibutuhkan' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ]);

        // Resolve branch_id
        $branchId = in_array($user->role, ['staff_purchasing', 'admin_cabang'])
            ? $user->branch_id
            : (int) $validated['branch_id'];

        if (empty($branchId)) {
            return response()->json(['message' => 'Branch ID is required.'], 422);
        }

        // Call EmployeeService to fetch branch details and validate active status
        $branch = $this->employeeService->getBranchByID($branchId);
        if (!$branch || !($branch->is_active ?? true)) {
            return response()->json([
                'message' => 'The selected branch is inactive or invalid.',
            ], 422);
        }

        $branchName = $branch->name;
        $branchCode = $branch->code;

        // Check vendor is active
        $vendor = Vendor::find($validated['vendor_id']);
        if (!$vendor || !$vendor->is_active) {
            return response()->json([
                'message' => 'The selected vendor is inactive.',
            ], 422);
        }

        $year = date('Y', strtotime($validated['tanggal_po']));

        DB::beginTransaction();
        try {
            // Call stored procedure to get the next sequential PO number
            DB::statement("CALL sp_next_po_number(?, ?, @po_number)", [$branchCode, $year]);
            $results = DB::select("SELECT @po_number AS po_number");
            $poNumber = $results[0]->po_number;

            if (empty($poNumber)) {
                throw new \Exception("Failed to generate PO number from stored procedure.");
            }

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'branch_id' => $branchId,
                'branch_name' => $branchName,
                'branch_code' => $branchCode,
                'vendor_id' => $validated['vendor_id'],
                'requested_by' => $user->id,
                'status' => 'draft',
                'tanggal_po' => $validated['tanggal_po'],
                'tanggal_dibutuhkan' => $validated['tanggal_dibutuhkan'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Purchase Order created successfully in draft status.',
                'data' => PurchaseOrder::with(['vendor'])->find($po->id)
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Create Purchase Order failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create Purchase Order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /purchase-orders/{id}/items
     * Add/Edit/Delete items. Only allowed in draft status.
     */
    public function updateItems(Request $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::find($id);

        if (!$po) {
            return response()->json(['message' => 'Purchase Order not found.'], 404);
        }

        $this->checkPOAccess($request, $po);

        if ($po->status !== 'draft') {
            return response()->json([
                'message' => 'Cannot modify items. Purchase Order is not in draft status.'
            ], 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        // Pre-validate all items are active
        foreach ($validated['items'] as $itemData) {
            $item = Item::find($itemData['item_id']);
            if (!$item || !$item->is_active) {
                return response()->json([
                    'message' => "Item '{$item->name}' is inactive and cannot be ordered."
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Delete existing items
            PurchaseOrderItem::where('purchase_order_id', $po->id)->delete();

            // Insert new items
            foreach ($validated['items'] as $itemData) {
                $item = Item::find($itemData['item_id']);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'item_id' => $itemData['item_id'],
                    'item_name' => $item->name,
                    'quantity' => $itemData['quantity'],
                    'unit' => $item->unit,
                    'unit_price' => $itemData['unit_price'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            DB::commit();

            // Reload PO details
            $poFresh = PurchaseOrder::with(['vendor', 'items.item'])->find($po->id);

            return response()->json([
                'message' => 'Purchase Order items updated successfully.',
                'data' => $poFresh
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Update PO items failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update Purchase Order items.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PATCH /purchase-orders/{id}/submit
     * Transition from draft to submitted.
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::find($id);

        if (!$po) {
            return response()->json(['message' => 'Purchase Order not found.'], 404);
        }

        $this->checkPOAccess($request, $po);

        if ($po->status !== 'draft') {
            return response()->json([
                'message' => "Invalid status transition. Cannot submit PO from status '{$po->status}'."
            ], 422);
        }

        $po->status = 'submitted';
        $po->save();

        return response()->json([
            'message' => 'Purchase Order submitted successfully.',
            'data' => PurchaseOrder::with(['vendor', 'items.item'])->find($po->id)
        ]);
    }

    /**
     * PATCH /purchase-orders/{id}/approve
     * Transition from submitted to approved. (Admin Purchasing/Superadmin only)
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $po = PurchaseOrder::find($id);

        if (!$po) {
            return response()->json(['message' => 'Purchase Order not found.'], 404);
        }

        if ($po->status !== 'submitted') {
            return response()->json([
                'message' => "Invalid status transition. Cannot approve PO from status '{$po->status}'."
            ], 422);
        }

        $po->status = 'approved';
        $po->approved_by = $user->id;
        $po->approved_at = Carbon::now();
        $po->save();

        return response()->json([
            'message' => 'Purchase Order approved successfully.',
            'data' => PurchaseOrder::with(['vendor', 'items.item'])->find($po->id)
        ]);
    }

    /**
     * PATCH /purchase-orders/{id}/reject
     * Transition from submitted to rejected. (Admin Purchasing/Superadmin only)
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $po = PurchaseOrder::find($id);

        if (!$po) {
            return response()->json(['message' => 'Purchase Order not found.'], 404);
        }

        if ($po->status !== 'submitted') {
            return response()->json([
                'message' => "Invalid status transition. Cannot reject PO from status '{$po->status}'."
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:1'],
        ]);

        $po->status = 'rejected';
        $po->rejection_reason = $validated['rejection_reason'];
        $po->approved_by = $user->id;
        $po->approved_at = Carbon::now();
        $po->save();

        return response()->json([
            'message' => 'Purchase Order rejected successfully.',
            'data' => PurchaseOrder::with(['vendor', 'items.item'])->find($po->id)
        ]);
    }

    /**
     * PATCH /purchase-orders/{id}/receive
     * Transition from approved to received. (Admin Cabang/Superadmin only)
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $po = PurchaseOrder::find($id);

        if (!$po) {
            return response()->json(['message' => 'Purchase Order not found.'], 404);
        }

        $this->checkPOAccess($request, $po);

        if ($po->status !== 'approved') {
            return response()->json([
                'message' => "Invalid status transition. Cannot receive PO from status '{$po->status}'."
            ], 422);
        }

        $po->status = 'received';
        $po->save();

        return response()->json([
            'message' => 'Purchase Order received successfully.',
            'data' => PurchaseOrder::with(['vendor', 'items.item'])->find($po->id)
        ]);
    }

    /**
     * PATCH /purchase-orders/{id}/cancel
     * Transition from draft or submitted to cancelled.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::find($id);

        if (!$po) {
            return response()->json(['message' => 'Purchase Order not found.'], 404);
        }

        $this->checkPOAccess($request, $po);

        if (!in_array($po->status, ['draft', 'submitted'])) {
            return response()->json([
                'message' => "Invalid status transition. Cannot cancel PO from status '{$po->status}'."
            ], 422);
        }

        $po->status = 'cancelled';
        $po->save();

        return response()->json([
            'message' => 'Purchase Order cancelled successfully.',
            'data' => PurchaseOrder::with(['vendor', 'items.item'])->find($po->id)
        ]);
    }
}
