<?php

namespace App\Models;

use App\Traits\HasBranchCatalog;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasBranchCatalog, HasFactory, UsesTenantConnection;

    protected $fillable = ['branch_id', 'catalog_group_id', 'name', 'slug', 'image', 'status'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            if (empty($brand->slug)) {
                $brand->slug = static::generateUniqueSlug($brand->name);
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
