<?php

namespace App\Models;

use App\Enums\PurchaseReceivedPayment;
use App\Enums\PurchaseType;
use App\Models\PurchaseProduct;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use  HasBranch;

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'date',
        'gross_amount',
        'discount',
        'vat',
        'paid_amount',
        'due_amount',
        'purchase_type',
        'comment',
        'payment_type',
        'serial',
    ];

    protected $casts = [
        'purchase_type' => PurchaseType::class,
        'payment_type' => PurchaseReceivedPayment::class
    ];


    public function getNetAmountAttribute()
    {
        return $this->gross_amount + $this->vat - $this->discount;
    }

    public function branch()
    {
        return $this->hasOne(Branch::class, 'id', 'branch_id');
    }

    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'id', 'supplier_id');
    }

    public function purchaseProducts()
    {
        return $this->hasMany(PurchaseProduct::class, 'purchase_id');
    }

    public function scopePurchase($q)
    {
        return $q->where('purchase_type',PurchaseType::Purchase);
    }
    public function scopeDamage($q)
    {
        return $q->where('purchase_type',PurchaseType::Damage);
    }

    public function scopePurchaseReturn($q)
    {
        return $q->where('purchase_type',PurchaseType::Purchase_Return);
    }

    public function scopeInitialStock($q)
    {
        return $q->where('purchase_type',PurchaseType::InitialStock);
    }



}
