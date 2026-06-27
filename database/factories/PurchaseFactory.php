<?php

namespace Database\Factories;

use App\Enums\PurchaseReceivedPayment;
use App\Enums\PurchaseType;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'supplier_id' => Supplier::factory(),
            'date' => now()->format('Y-m-d'),
            'gross_amount' => 0,
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
            'purchase_type' => PurchaseType::Purchase,
            'payment_type' => PurchaseReceivedPayment::Cash,
            'comment' => null,
            'serial' => 'INVP'.str_pad((string) (Purchase::max('id') + 1), 8, '0', STR_PAD_LEFT),
        ];
    }

    public function purchase(): static
    {
        return $this->state(['purchase_type' => PurchaseType::Purchase]);
    }

    public function withSupplier(Supplier $supplier): static
    {
        return $this->state([
            'supplier_id' => $supplier->id,
            'branch_id' => $supplier->branch_id,
        ]);
    }

    public function withUser(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
            'branch_id' => $user->branch_id,
        ]);
    }

    public function withAmounts(float $gross, float $discount = 0, float $vat = 0, float $paid = 0): static
    {
        $net = $gross + $vat - $discount;

        return $this->state([
            'gross_amount' => $gross,
            'discount' => $discount,
            'vat' => $vat,
            'paid_amount' => $paid,
            'due_amount' => max(0, $net - $paid),
        ]);
    }
}
