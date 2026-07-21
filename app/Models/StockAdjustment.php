<?php

namespace App\Models;

use App\Enums\StockAdjustmentType;
use App\Traits\HasBranchInvoiceNumber;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use HasBranchInvoiceNumber, HasBranchUser;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'date',
        'type',
        'serial',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => StockAdjustmentType::class,
    ];

    public static function invoicePrefix(): string
    {
        return 'INVA';
    }

    public function products(): HasMany
    {
        return $this->hasMany(StockAdjustmentProduct::class);
    }

    public function isIncrease(): bool
    {
        return $this->type === StockAdjustmentType::Increase;
    }
}
