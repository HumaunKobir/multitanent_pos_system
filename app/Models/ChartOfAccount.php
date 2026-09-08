<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Services\SystemAccountService;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class ChartOfAccount extends Model
{
    use SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'source_type',
        'source_id',
        'parent_id',
        'code',
        'account_number',
        'name',
        'type',
        'current_balance',
        'description',
        'status',
        'is_system',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'current_balance' => 'decimal:2',
        'is_system' => 'boolean',
        'status' => CommonStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | POLYMORPHIC OWNERSHIP
    |--------------------------------------------------------------------------
    */

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | TREE RELATION
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class, 'account_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeForSource($query, $type, $id)
    {
        return $query->where('source_type', $type)
            ->where('source_id', $id);
    }

    public function scopeForPanel(Builder $query): Builder
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null || Branch::isMainBranch($branchId)) {
            return $query->whereNull('source_type')->whereNull('source_id');
        }

        return $query->where('source_type', Branch::class)->where('source_id', $branchId);
    }

    /**
     * @return array{source_type: class-string<Model>|null, source_id: int|null}
     */
    public static function panelSourceAttributes(?int $branchId = null): array
    {
        if ($branchId === null || Branch::isMainBranch($branchId)) {
            return [
                'source_type' => null,
                'source_id' => null,
            ];
        }

        return [
            'source_type' => Branch::class,
            'source_id' => $branchId,
        ];
    }

    public function scopeAccount($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopePaymentAccount($query)
    {
        return $query
            ->forPanel()
            ->where('type', AccountType::Asset)
            ->where('parent_id', SystemAccountService::id(SystemAccountKey::CashAndBank, Auth::user()?->branch_id))
            ->where('status', CommonStatus::Active);
    }

    public function isPaymentAccount(): bool
    {
        return $this->type === AccountType::Asset
            && $this->parent_id === SystemAccountService::id(SystemAccountKey::CashAndBank, Auth::user()?->branch_id);
    }

    /*
    |--------------------------------------------------------------------------
    | CODE GENERATION ENGINE
    |--------------------------------------------------------------------------
    */

    public static bool $skipCodeGeneration = false;

    protected static function booted()
    {
        static::creating(function (ChartOfAccount $account) {

            if (self::$skipCodeGeneration) {
                return;
            }

            // code already provided হলে skip
            if (filled($account->code)) {
                return;
            }

            $prefix = self::prefixForType($account->type);

            if (blank($account->parent_id)) {
                $account->code = self::nextParentCode($prefix);
            } else {
                $account->code = self::nextChildCode($account->parent_id, $prefix);
            }
        });
    }

    protected static function prefixForType(AccountType $type): string
    {
        return match ($type) {
            AccountType::Asset => 'A',
            AccountType::Liability => 'L',
            AccountType::Equity => 'E',
            AccountType::Income => 'I',
            AccountType::Expenses => 'X',
        };
    }

    public static function previewCode(AccountType $type, ?int $parentId): string
    {
        $prefix = self::prefixForType($type);

        return $parentId
            ? self::nextChildCode($parentId, $prefix)
            : self::nextParentCode($prefix);
    }

    protected static function nextParentCode(string $prefix): string
    {
        $last = static::query()
            ->forPanel()
            ->whereNull('parent_id')
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->first();

        if (! $last) {
            return sprintf('%s%03d', $prefix, 1);
        }

        $num = (int) preg_replace('/^\D+/', '', $last->code);

        return sprintf('%s%03d', $prefix, $num + 1);
    }

    protected static function nextChildCode(int $parentId, string $prefix): string
    {
        $parent = static::query()->findOrFail($parentId);

        $base = $parent->code;

        $lastChild = static::query()
            ->forPanel()
            ->where('parent_id', $parent->id)
            ->where('code', 'like', $base.'-%')
            ->orderByDesc('code')
            ->first();

        if (! $lastChild) {
            return $base.'-'.sprintf('%02d', 1);
        }

        $parts = explode('-', $lastChild->code);
        $suffix = end($parts);
        $num = (int) preg_replace('/\D/', '', $suffix);

        return $base.'-'.sprintf('%02d', $num + 1);
    }
}
