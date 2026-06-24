<?php

namespace App\Models;

use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinSettings extends Model
{
    use HasBranch;

    protected $fillable = [
        'branch_id',
        'enabled',
        'earn_spend_amount',
        'earn_coins',
        'coin_value',
        'min_redeem_coins',
        'max_redeem_percent',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'earn_spend_amount' => 'decimal:2',
            'earn_coins' => 'decimal:2',
            'coin_value' => 'decimal:2',
            'min_redeem_coins' => 'decimal:2',
            'max_redeem_percent' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isActive(): bool
    {
        return $this->enabled
            && (float) $this->earn_spend_amount > 0
            && (float) $this->earn_coins > 0
            && (float) $this->coin_value > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPosPayload(): array
    {
        return [
            'enabled' => $this->isActive(),
            'earn_spend_amount' => (float) $this->earn_spend_amount,
            'earn_coins' => (float) $this->earn_coins,
            'coin_value' => (float) $this->coin_value,
            'min_redeem_coins' => (float) $this->min_redeem_coins,
            'max_redeem_percent' => (float) $this->max_redeem_percent,
        ];
    }
}
