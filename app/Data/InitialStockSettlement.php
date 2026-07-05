<?php

namespace App\Data;

readonly class InitialStockSettlement
{
    public function __construct(
        public ?int $supplierId,
        public float $paidAmount,
        public ?int $paymentAccountId,
    ) {}

    public function usesSupplier(): bool
    {
        return $this->supplierId !== null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        $supplierId = filled($data['initial_stock_supplier_id'] ?? null)
            ? (int) $data['initial_stock_supplier_id']
            : null;

        $paidAmount = round(max(0, (float) ($data['initial_stock_paid_amount'] ?? 0)), 2);

        $paymentAccountId = filled($data['initial_stock_payment_account_id'] ?? null)
            ? (int) $data['initial_stock_payment_account_id']
            : null;

        if ($supplierId === null) {
            $paidAmount = 0.0;
            $paymentAccountId = null;
        }

        return new self($supplierId, $paidAmount, $paymentAccountId);
    }
}
