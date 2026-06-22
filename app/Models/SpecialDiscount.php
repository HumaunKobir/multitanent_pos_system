<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecialDiscount extends Model
{
    use HasBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'min_amount',
        'max_amount',
        'discount_type',
        'discount_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function sells(): HasMany
    {
        $relation = $this->hasMany(Sell::class);

        if ($this->branch_id !== null) {
            $relation->where('branch_id', $this->branch_id);
        }

        return $relation;
    }

    public function productExchanges(): HasMany
    {
        $relation = $this->hasMany(ProductExchange::class);

        if ($this->branch_id !== null) {
            $relation->where('branch_id', $this->branch_id);
        }

        return $relation;
    }

    public function hasRecordedUsage(): bool
    {
        return $this->sells()->exists()
            || $this->productExchanges()->exists();
    }

    public function recordedUsageCount(): int
    {
        return $this->sells()->count()
            + $this->productExchanges()->count();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function appliesToAmount(float $amount): bool
    {
        if ($amount < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $amount > (float) $this->max_amount) {
            return false;
        }

        return true;
    }

    public function amountLabel(): string
    {
        if ($this->max_amount !== null) {
            return sprintf(
                '৳%s – ৳%s',
                number_format((float) $this->min_amount, 2),
                number_format((float) $this->max_amount, 2),
            );
        }

        return sprintf('৳%s+', number_format((float) $this->min_amount, 2));
    }

    public function discountLabel(): string
    {
        return match ($this->discount_type) {
            DiscountType::Percent => number_format((float) $this->discount_value, 2).'% off',
            DiscountType::Flat => '৳'.number_format((float) $this->discount_value, 2).' off',
        };
    }
}
