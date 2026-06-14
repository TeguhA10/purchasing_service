<?php

use App\Http\Controllers\VendorController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

// All endpoints in Purchasing Service require JWT authentication
Route::middleware('jwt.auth')->group(function () {

    // Vendor Management (All authenticated roles can read)
    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/{id}', [VendorController::class, 'show']);
    Route::get('/vendors/{id}/purchase-history', [VendorController::class, 'purchaseHistory']);

    // Item Management (All authenticated roles can read)
    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{id}', [ItemController::class, 'show']);

    // Purchase Order Management (All authenticated roles can create/manage draft/view/cancel)
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::put('/purchase-orders/{id}/items', [PurchaseOrderController::class, 'updateItems']);
    Route::patch('/purchase-orders/{id}/submit', [PurchaseOrderController::class, 'submit']);
    Route::patch('/purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);

    // Admin Purchasing / Superadmin restricted routes
    Route::middleware('jwt.admin_purchasing')->group(function () {
        // Vendor management writes
        Route::post('/vendors', [VendorController::class, 'store']);
        Route::put('/vendors/{id}', [VendorController::class, 'update']);
        Route::patch('/vendors/{id}/deactivate', [VendorController::class, 'deactivate']);
        Route::patch('/vendors/{id}/activate', [VendorController::class, 'activate']);

        // Item management writes
        Route::post('/items', [ItemController::class, 'store']);
        Route::put('/items/{id}', [ItemController::class, 'update']);
        Route::patch('/items/{id}/deactivate', [ItemController::class, 'deactivate']);
        Route::patch('/items/{id}/activate', [ItemController::class, 'activate']);

        // PO approvals/rejections
        Route::patch('/purchase-orders/{id}/approve', [PurchaseOrderController::class, 'approve']);
        Route::patch('/purchase-orders/{id}/reject', [PurchaseOrderController::class, 'reject']);
    });

    // Admin Cabang / Superadmin restricted routes
    Route::middleware('jwt.admin_cabang')->group(function () {
        // PO receive
        Route::patch('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
    });

});