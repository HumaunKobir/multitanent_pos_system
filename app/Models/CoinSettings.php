<?php

namespace App\Models;

use App\Enums\CoinExpiryUnit;
use App\Traits\HasBranch;
use App\Traits\UsesTenantConnection;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CoinSettings extends Model
{
    use HasBranch, UsesTenantConnection;

    protected $fillable = [
        'branch_id',
        'enabled',
        'earn_spend_amount',
        'earn_coins',
        'coin_value',
        'min_redeem_coins',
        'max_redeem_percent',
        'expiry_value',
        'expiry_unit',
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
            'expiry_value' => 'integer',
            'expiry_unit' => CoinExpiryUnit::class,
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

    public function resolveExpiresAt(?CarbonInterface $from = null): ?Carbon
    {
        if ($this->expiry_value === null || $this->expiry_unit === null || $this->expiry_value < 1) {
            return null;
        }

        $from = Carbon::parse($from ?? now());

        return match ($this->expiry_unit) {
            CoinExpiryUnit::Day => $from->copy()->addDays($this->expiry_value),
            CoinExpiryUnit::Week => $from->copy()->addWeeks($this->expiry_value),
            CoinExpiryUnit::Month => $from->copy()->addMonths($this->expiry_value),
            CoinExpiryUnit::Year => $from->copy()->addYears($this->expiry_value),
        };
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
            'expiry_value' => $this->expiry_value,
            'expiry_unit' => $this->expiry_unit?->value,
        ];
    }
}
