<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Services\EcommerceBranchService;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'catalog_group_id',
        'parent_id',
        'name',
        'image',
        'status',
    ];

    protected $appends = [
        'status_label',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommonStatus::class,
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->name ?? CommonStatus::Pending->name;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeForPanel(Builder $query): Builder
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null) {
            return $query->where('branch_id', $branchId);
        }

        return $query->where('branch_id', Branch::resolveAdminCatalogBranchId());
    }

    public function scopeSelectableForProduct(Builder $query): Builder
    {
        return $query
            ->forPanel()
            ->whereIn('status', [CommonStatus::Active, CommonStatus::Pending]);
    }

    public function scopeForStorefront(Builder $query): Builder
    {
        return $query->where('branch_id', EcommerceBranchService::resolveIdStatic());
    }
}
