<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_number',
        'branch_id',
        'branch_name',
        'branch_code',
        'vendor_id',
        'requested_by',
        'status',
        'tanggal_po',
        'tanggal_dibutuhkan',
        'tanggal_pengiriman',
        'total_amount',
        'catatan',
        'rejection_reason',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'branch_id' => 'integer',
            'vendor_id' => 'integer',
            'requested_by' => 'integer',
            'total_amount' => 'decimal:2',
            'approved_by' => 'integer',
            'tanggal_po' => 'date:Y-m-d',
            'tanggal_dibutuhkan' => 'date:Y-m-d',
            'tanggal_pengiriman' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relationship to Vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Relationship to Purchase Order Items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }
}
