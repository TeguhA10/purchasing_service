<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{

    /**
     * GET /items
     * List items with search, filters, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Item::with(['defaultVendor']);

        // Filter: category
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category', $request->category);
        }

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

        $items = $query->skip(($page - 1) * $limit)
                       ->take($limit)
                       ->orderBy('name', 'asc')
                       ->get();

        return response()->json([
            'message' => 'Success',
            'data' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ]
        ]);
    }

    /**
     * GET /items/{id}
     * Get item details.
     */
    public function show(int $id): JsonResponse
    {
        $item = Item::with(['defaultVendor'])->find($id);

        if (!$item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $item
        ]);
    }

    /**
     * POST /items
     * Create a new item.
     */
    public function store(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:items,code'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'default_vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item = Item::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'unit' => $validated['unit'],
            'default_vendor_id' => $validated['default_vendor_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Item created successfully.',
            'data' => Item::with(['defaultVendor'])->find($item->id)
        ], 201);
    }

    /**
     * PUT /items/{id}
     * Update an item.
     */
    public function update(Request $request, int $id): JsonResponse
    {

        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('items', 'code')->ignore($item->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'default_vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $item->fill($validated);
        $item->save();

        return response()->json([
            'message' => 'Item updated successfully.',
            'data' => Item::with(['defaultVendor'])->find($item->id)
        ]);
    }

    /**
     * PATCH /items/{id}/deactivate
     * Deactivate an item.
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {

        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $item->is_active = false;
        $item->save();

        return response()->json([
            'message' => 'Item deactivated successfully.',
            'data' => Item::with(['defaultVendor'])->find($item->id)
        ]);
    }

    /**
     * PATCH /items/{id}/activate
     * Activate an item.
     */
    public function activate(Request $request, int $id): JsonResponse
    {

        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $item->is_active = true;
        $item->save();

        return response()->json([
            'message' => 'Item activated successfully.',
            'data' => Item::with(['defaultVendor'])->find($item->id)
        ]);
    }
}
