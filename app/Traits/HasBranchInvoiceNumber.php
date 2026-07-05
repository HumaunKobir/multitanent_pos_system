<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasBranchInvoiceNumber
{
    abstract public static function invoicePrefix(): string;

    public function initializeHasBranchInvoiceNumber(): void
    {
        $this->appends = array_values(array_unique([...$this->appends, 'invoice_number']));
    }

    protected static function bootHasBranchInvoiceNumber(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->shouldAssignInvoiceSequence()) {
                return;
            }

            $model->assignInvoiceSequence();
        });

        static::updating(function (Model $model): void {
            if ($model->invoice_sequence !== null) {
                return;
            }

            if (! $model->shouldAssignInvoiceSequence()) {
                return;
            }

            $model->assignInvoiceSequence();
        });
    }

    public function shouldAssignInvoiceSequence(): bool
    {
        return true;
    }

    public function assignInvoiceSequence(): void
    {
        if ($this->invoice_sequence !== null) {
            return;
        }

        $this->invoice_sequence = static::nextInvoiceSequence((int) $this->branch_id);

        if (in_array('serial', $this->getFillable(), true)) {
            $this->serial = static::formatInvoiceNumber((int) $this->invoice_sequence);
        }
    }

    public static function nextInvoiceSequence(?int $branchId): int
    {
        if ($branchId === null) {
            return 1;
        }

        $max = static::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('invoice_sequence')
            ->orderByDesc('invoice_sequence')
            ->lockForUpdate()
            ->value('invoice_sequence');

        return ((int) $max) + 1;
    }

    public static function formatInvoiceNumber(int $sequence): string
    {
        return static::invoicePrefix().str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
    }

    public function getInvoiceNumberAttribute(): string
    {
        if ($this->invoice_sequence !== null) {
            return static::formatInvoiceNumber((int) $this->invoice_sequence);
        }

        if (isset($this->attributes['serial']) && filled($this->attributes['serial'])) {
            return (string) $this->attributes['serial'];
        }

        return static::formatInvoiceNumber((int) $this->id);
    }

    public static function extractInvoiceSequence(string $invoice): ?int
    {
        $normalized = strtoupper(trim($invoice));

        if (preg_match('/(\d+)$/', $normalized, $matches)) {
            return (int) $matches[1];
        }

        if (ctype_digit($normalized)) {
            return (int) $normalized;
        }

        return null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForInvoiceSequence(Builder $query, string $invoice): Builder
    {
        $sequence = static::extractInvoiceSequence($invoice);

        if ($sequence === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('invoice_sequence', $sequence);
    }
}
