<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSectionItem extends Model
{
    protected $fillable = [
        'product_section_id', 'product_id',
        'block_type', 'image', 'link', 'sort_order',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProductSection::class, 'product_section_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
