<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes, UsesTenantConnection;

    protected $fillable = ['source_type', 'source_id', 'performed_by_type', 'performed_by_id', 'date', 'amount', 'debit_account_id', 'credit_account_id', 'debit_decrease', 'credit_decrease', 'description', 'approved_at', 'business_session_id'];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
        'debit_decrease' => 'boolean',
        'credit_decrease' => 'boolean',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function performedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function businessSession(): BelongsTo
    {
        return $this->belongsTo(BusinessSession::class);
    }
}
