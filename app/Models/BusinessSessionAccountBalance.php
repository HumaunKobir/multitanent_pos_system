<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSessionAccountBalance extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'business_session_id',
        'account_id',
        'account_name',
        'account_type',
        'opening_balance',
        'total_debit',
        'total_credit',
        'total_received',
        'total_paid',
        'total_transfer_in',
        'total_transfer_out',
        'closing_balance',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'opening_balance' => 'decimal:2',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'total_received' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'total_transfer_in' => 'decimal:2',
            'total_transfer_out' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function businessSession(): BelongsTo
    {
        return $this->belongsTo(BusinessSession::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
