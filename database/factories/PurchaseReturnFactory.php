<?php

namespace Database\Factories;

use App\Enums\PurchaseReceivedPayment;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    protected $model = PurchaseReturn::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'purchase_id' => Purchase::factory(),
            'supplier_id' => Supplier::factory(),
            'date' => now()->format('Y-m-d'),
            'gross_amount' => 0,
            'discount' => 0,
            'vat' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
            'payment_type' => PurchaseReceivedPayment::Cash,
            'comment' => null,
        ];
    }

    public function forPurchase(Purchase $purchase): static
    {
        return $this->state([
            'purchase_id' => $purchase->id,
            'supplier_id' => $purchase->supplier_id,
            'branch_id' => $purchase->branch_id,
        ]);
    }
}
