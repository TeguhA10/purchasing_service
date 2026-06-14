<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{

    /**
     * GET /vendors
     * List vendors with filters, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::query();

        // Filter: is_active
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active') ? 1 : 0);
        }

        // Search: name or code
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Pagination
        $limit = (int) $request->query('limit', 10);
        $page = (int) $request->query('page', 1);
        $total = $query->count();
        $totalPages = (int) ceil($total / $limit);

        $vendors = $query->skip(($page - 1) * $limit)
                         ->take($limit)
                         ->orderBy('name', 'asc')
                         ->get();

        return response()->json([
            'message' => 'Success',
            'data' => $vendors,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ]
        ]);
    }

    /**
     * GET /vendors/{id}
     * Get vendor details.
     */
    public function show(int $id): JsonResponse
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $vendor
        ]);
    }

    /**
     * POST /vendors
     * Create a new vendor.
     */
    public function store(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'unique:vendors,code'],
            'contact_person' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['required', 'string'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $vendor = Vendor::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'contact_person' => $validated['contact_person'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'],
            'npwp' => $validated['npwp'] ?? null,
            'payment_term_days' => $validated['payment_term_days'] ?? 30,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Vendor created successfully.',
            'data' => $vendor
        ], 201);
    }

    /**
     * PUT /vendors/{id}
     * Update an existing vendor.
     */
    public function update(Request $request, int $id): JsonResponse
    {

        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('vendors', 'code')->ignore($vendor->id),
            ],
            'contact_person' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['sometimes', 'required', 'string'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $vendor->fill($validated);
        $vendor->save();

        return response()->json([
            'message' => 'Vendor updated successfully.',
            'data' => $vendor
        ]);
    }

    /**
     * PATCH /vendors/{id}/deactivate
     * Deactivate a vendor.
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {

        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        $vendor->is_active = false;
        $vendor->save();

        return response()->json([
            'message' => 'Vendor deactivated successfully.',
            'data' => $vendor
        ]);
    }

    /**
     * PATCH /vendors/{id}/activate
     * Activate a vendor.
     */
    public function activate(Request $request, int $id): JsonResponse
    {

        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        $vendor->is_active = true;
        $vendor->save();

        return response()->json([
            'message' => 'Vendor activated successfully.',
            'data' => $vendor
        ]);
    }

    /**
     * GET /vendors/{id}/purchase-history
     * Get Purchase Order history for a vendor.
     */
    public function purchaseHistory(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        $user = $request->attributes->get('auth_user');

        $query = PurchaseOrder::where('vendor_id', $id);

        // RBAC: staff_purchasing & admin_cabang can only see their own branch POs
        if (in_array($user->role, ['staff_purchasing', 'admin_cabang'])) {
            $query->where('branch_id', $user->branch_id);
        }

        // Pagination
        $limit = (int) $request->query('limit', 10);
        $page = (int) $request->query('page', 1);
        $total = $query->count();
        $totalPages = (int) ceil($total / $limit);

        $pos = $query->skip(($page - 1) * $limit)
                     ->take($limit)
                     ->orderBy('tanggal_po', 'desc')
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
}
