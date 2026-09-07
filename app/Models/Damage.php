<?php

namespace App\Models;

use App\Traits\HasBranchInvoiceNumber;
use App\Traits\HasBranchUser;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Damage extends Model
{
    use HasBranchInvoiceNumber, HasBranchUser, UsesTenantConnection;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'date',
        'serial',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public static function invoicePrefix(): string
    {
        return 'INVD';
    }

    public function products(): HasMany
    {
        return $this->hasMany(DamageProduct::class);
    }
}
