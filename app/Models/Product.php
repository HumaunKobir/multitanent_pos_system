<?php

namespace App\Models;

use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'category_id',
        'brand_id',
        'unit_id',
        'warranty_id',
        'name',
        'slug',
        'code',
        'purchase_price',
        'sale_price',
        'discount_price',
        'tags',
        'image',
        'youtube_link',
        'description',
        'delivery_info',
        'visible',
        'status',
    ];

    protected $casts = [
        'tags' => 'array',
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getEffectivePriceAttribute(): string
    {
        return $this->discount_price > 0 ? $this->discount_price : $this->sale_price;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', 'yes');
    }

    public function scopeBranchWise(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForPurchase(Builder $query): Builder
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null || Branch::isMainBranch($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function resolveStockBranchId(?int $actingBranchId = null): ?int
    {
        return $this->branch_id ?? $actingBranchId;
    }

    public function purchaseProducts(): HasMany
    {
        return $this->hasMany(PurchaseProduct::class);
    }

    public function sellProducts(): HasMany
    {
        return $this->hasMany(SellProduct::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'like', $slug.'%')->count();

        return $count > 0 ? $slug.'-'.($count + 1) : $slug;
    }
}
