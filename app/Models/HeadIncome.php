<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeadIncome extends Model
{
    use UsesTenantConnection;

    protected $fillable = ['income_id', 'head_id', 'amount', 'description'];

    protected $casts = ['amount' => 'decimal:2'];

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'head_id');
    }
}
