<?php

namespace App\Models;

use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Damage extends Model
{
    use HasBranch;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'date',
        'serial',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVD'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function products(): HasMany
    {
        return $this->hasMany(DamageProduct::class);
    }
}
