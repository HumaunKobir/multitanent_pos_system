<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ledger extends Model
{
    use SoftDeletes, UsesTenantConnection;

    protected $fillable = ['account_id', 'transaction_id', 'line_performed_by_type', 'line_performed_by_id', 'date', 'opening_balance', 'debit', 'credit', 'closing_balance', 'description', 'source_type', 'source_id'];

    protected $casts = [
        'date' => 'date',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function linePerformedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
