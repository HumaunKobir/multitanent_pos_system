<?php

namespace App\Models;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Traits\HasBranch;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
    use HasBranch, HasFactory, UsesTenantConnection;

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'scope',
        'type',
        'discount_value',
        'fixed_price',
        'buy_qty',
        'get_qty',
        'get_discount_percent',
        'bundle_product_ids',
        'min_qty',
        'starts_at',
        'ends_at',
        'status',
        'priority',
        'stack_with_product_discount',
        'stack_with_manual_line_discount',
        'stack_with_invoice_discount',
        'stack_with_special_discount',
        'exclusive',
    ];

    protected function casts(): array
    {
        return [
            'scope' => PromotionScope::class,
            'type' => PromotionType::class,
            'discount_value' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'get_discount_percent' => 'decimal:2',
            'bundle_product_ids' => 'array',
            'min_qty' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => 'boolean',
            'stack_with_product_discount' => 'boolean',
            'stack_with_manual_line_discount' => 'boolean',
            'stack_with_invoice_discount' => 'boolean',
            'stack_with_special_discount' => 'boolean',
            'exclusive' => 'boolean',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function sellProducts(): HasMany
    {
        return $this->hasMany(SellProduct::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeWithinSchedule(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $query
            ->where(function (Builder $query) use ($at) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $at);
            });
    }

    public function scopeActiveOnSaleDate(Builder $query, string|\DateTimeInterface|null $saleDate): Builder
    {
        if ($saleDate === null || $saleDate === '') {
            return $query->withinSchedule(now());
        }

        $date = Carbon::parse($saleDate);

        if ($date->isToday()) {
            return $query->withinSchedule(now());
        }

        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        return $query
            ->where(function (Builder $query) use ($endOfDay) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $endOfDay);
            })
            ->where(function (Builder $query) use ($startOfDay) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $startOfDay);
            });
    }

    public function hasRecordedUsage(): bool
    {
        return $this->sellProducts()->exists();
    }

    public function recordedUsageCount(): int
    {
        return $this->sellProducts()->count();
    }

    public function typeLabel(): string
    {
        return $this->type->label();
    }

    public function scopeLabel(): string
    {
        return $this->scope->label();
    }

    public function discountSummary(): string
    {
        return match ($this->type) {
            PromotionType::Percent => number_format((float) $this->discount_value, 2).'% off',
            PromotionType::Flat => '৳'.number_format((float) $this->discount_value, 2).' off',
            PromotionType::FixedPrice => '৳'.number_format((float) $this->fixed_price, 2).' fixed',
            PromotionType::BuyXGetY => "Buy {$this->buy_qty} Get {$this->get_qty}",
            PromotionType::Bundle => 'Bundle discount',
        };
    }
}
