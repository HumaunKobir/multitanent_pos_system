<?php

namespace App\Traits;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAccount
{
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function incrementBalance(
        TransactionType $type,
        float $amount,
        string $note,
        string $date,
        mixed $reference = null,
        ?int $branchId = null
    ): void {
        $this->modifyBalance($type, $amount, $note, $date, $reference, $branchId);
        $this->increment('balance_in', $amount);
    }

    public function decrementBalance(
        TransactionType $type,
        float $amount,
        string $note,
        string $date,
        mixed $reference = null,
        ?int $branchId = null
    ): void {
        $this->modifyBalance($type, -$amount, $note, $date, $reference, $branchId);
        $this->increment('balance_out', $amount);
    }

    private function modifyBalance(
        TransactionType $type,
        float $signedAmount,
        string $note,
        string $date,
        mixed $reference = null,
        ?int $branchId = null
    ): void {
        $this->increment('balance', $signedAmount);
        $this->refresh();

        $this->transactions()->create([
            'branch_id' => $branchId,
            'type' => $type->value,
            'amount' => $signedAmount,
            'balance' => $this->balance,
            'note' => $note,
            'date' => $date,
            'reference_type' => $reference !== null ? get_class($reference) : null,
            'reference_id' => $reference?->getKey(),
        ]);
    }
}
