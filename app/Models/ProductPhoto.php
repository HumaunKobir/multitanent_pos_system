<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPhoto extends Model
{
    use UsesTenantConnection;

    protected $fillable = ['product_id', 'image'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
