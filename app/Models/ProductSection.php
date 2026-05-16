<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ProductSection extends Model
{
    protected $fillable = [
        'name', 'description', 'button_text', 'images', 'items',
        'block_per_line', 'layout_type', 'block_type', 'status', 'serial',
    ];

    protected $casts = [
        'images' => 'array',
        'items' => 'array',
    ];

    public function scopeActive($query): Builder
    {
        return $query->where('status', 1);
    }

    public function getProductItemsAttribute(): Collection
    {
        if (empty($this->items) || $this->block_type !== 2) {
            return collect();
        }

        return Product::with(['photos', 'variations'])
            ->whereIn('id', $this->items)
            ->where('status', 1)
            ->get();
    }
}
