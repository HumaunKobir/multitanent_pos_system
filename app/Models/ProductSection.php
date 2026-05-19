<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Enums\LayoutType;
use Database\Factories\ProductSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductSection extends Model
{
    /** @use HasFactory<ProductSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'button_text',
        'images',
        'items',
        'block_per_line',
        'layout_type',
        'block_type',
        'status',
        'serial',
    ];

    protected $appends = [
        'layout_type_label',
        'block_type_label',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'items' => 'array',
            'layout_type' => LayoutType::class,
            'block_type' => BlockType::class,
            'status' => 'integer',
            'block_per_line' => 'integer',
            'serial' => 'integer',
        ];
    }

    public function getLayoutTypeLabelAttribute(): string
    {
        return $this->layout_type?->name ?? '';
    }

    public function getBlockTypeLabelAttribute(): string
    {
        return $this->block_type?->name ?? '';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeForPanel(Builder $query): Builder
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return $query->whereNull('branch_id');
        }

        return $query->where('branch_id', $branchId);
    }

    public function getProductItemsAttribute(): Collection
    {
        if ($this->block_type !== BlockType::Item || empty($this->items)) {
            return collect();
        }

        return Product::with(['photos', 'variations'])
            ->whereIn('id', $this->items)
            ->where('status', 1)
            ->get();
    }

    /**
     * @return list<string>
     */
    public function getProductImagesAttribute(): array
    {
        if ($this->block_type !== BlockType::Image || empty($this->images)) {
            return [];
        }

        return collect($this->images)
            ->map(fn (array $image): ?string => ! empty($image['image'])
                ? Storage::disk('public')->url($image['image'])
                : null)
            ->filter()
            ->values()
            ->all();
    }
}
