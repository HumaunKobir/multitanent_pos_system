<?php

namespace App\Models;

use App\Enums\VoucherLineSide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherLine extends Model
{
    protected $fillable = [
        'voucher_id',
        'side',
        'account_id',
        'amount',
        'narration',
        'sort_order',
    ];

    protected $casts = [
        'side' => VoucherLineSide::class,
        'amount' => 'decimal:2',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
