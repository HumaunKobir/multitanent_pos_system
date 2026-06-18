<?php

namespace App\Models;

use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'product_group_id',
        'source_branch_id',
        'received_at',
        'category_id',
        'brand_id',
        'unit_id',
        'warranty_id',
        'colors',
        'sizes',
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
        'colors' => 'array',
        'sizes' => 'array',
        'received_at' => 'datetime',
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

    public function scopeVisibleInMainCatalog(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('source_branch_id')
                ->orWhereNotNull('received_at');
        });
    }

    public function scopePendingMainReceive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('source_branch_id')
            ->whereNull('received_at');
    }

    public function isPendingMainReceive(): bool
    {
        return $this->source_branch_id !== null && $this->received_at === null;
    }

    /**
     * @return array{quantity: int, variation_quantity: int, total: int}|null
     */
    public function submissionBranchStockSummary(): ?array
    {
        if ($this->source_branch_id === null) {
            return null;
        }

        $branchProduct = $this->siblingForBranch((int) $this->source_branch_id);

        if ($branchProduct === null) {
            return null;
        }

        $branchProduct->loadMissing(['initialStockRecord', 'variations:id,product_id,stock']);

        $nonVariantQuantity = (int) ($branchProduct->initialStockRecord?->quantity ?? 0);
        $variationQuantity = (int) $branchProduct->variations->sum('stock');

        return [
            'quantity' => $nonVariantQuantity,
            'variation_quantity' => $variationQuantity,
            'total' => $nonVariantQuantity + $variationQuantity,
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', 'yes');
    }

    public function scopeBranchWise(Builder $query, int $branchId): Builder
    {
        return $query->accessibleAtBranch($branchId);
    }

    public function scopeForPurchase(Builder $query): Builder
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    public function scopeAtBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function siblingForBranch(int $branchId): ?self
    {
        if ($this->branch_id === $branchId) {
            return $this;
        }

        if ($this->product_group_id === null) {
            return null;
        }

        return static::query()
            ->where('product_group_id', $this->product_group_id)
            ->where('branch_id', $branchId)
            ->first();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
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

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function scopeWithReviewSummary(Builder $query): Builder
    {
        return $query
            ->withAvg(['reviews as reviews_avg_rating' => fn (Builder $query) => $query->approved()], 'rating')
            ->withCount(['reviews as reviews_count' => fn (Builder $query) => $query->approved()]);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function initialStockRecord(): HasOne
    {
        return $this->hasOne(ProductInitialStock::class)->whereNull('product_variation_id');
    }

    public function resolveStockBranchId(?int $actingBranchId = null): int
    {
        if ($actingBranchId !== null) {
            return $actingBranchId;
        }

        if ($this->branch_id !== null) {
            return (int) $this->branch_id;
        }

        return Branch::resolveMainBranchId();
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

        static::creating(function (Product $product) {
            if (blank(trim((string) ($product->code ?? '')))) {
                $product->code = filled($product->slug)
                    ? $product->slug
                    : static::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        $prefix = 'PRD-';

        $maxNumber = static::query()
            ->where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->map(fn (string $code): int => (int) Str::after($code, $prefix))
            ->max() ?? 0;

        do {
            $maxNumber++;
            $code = $prefix.$maxNumber;
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = static::where('slug', 'like', $slug.'%')->count();

        return $count > 0 ? $slug.'-'.($count + 1) : $slug;
    }
}
