<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $table = 'items';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'unit',
        'default_vendor_id',
        'last_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'default_vendor_id' => 'integer',
            'last_price' => 'decimal:2',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relationship to default Vendor.
     */
    public function defaultVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'default_vendor_id');
    }

    /**
     * Relationship to Purchase Order Items.
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'item_id');
    }
}
