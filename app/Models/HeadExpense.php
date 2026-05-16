<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeadExpense extends Model
{
    protected $fillable = ['expense_id', 'head_id', 'amount', 'description'];

    protected $casts = ['amount' => 'decimal:2'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'head_id');
    }
}
