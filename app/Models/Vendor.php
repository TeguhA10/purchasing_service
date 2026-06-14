<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $table = 'vendors';

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'address',
        'npwp',
        'payment_term_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'payment_term_days' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relationship to Items where this vendor is the default supplier.
     */
    public function defaultItems(): HasMany
    {
        return $this->hasMany(Item::class, 'default_vendor_id');
    }

    /**
     * Relationship to Purchase Orders.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'vendor_id');
    }
}
