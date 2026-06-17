<?php

namespace App\Services;

use App\Enums\CustomerDueAlertStatus;
use App\Models\CustomerDueAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CustomerDueAlertService
{
    public function findActiveAlert(int $customerId, ?int $branchId): ?CustomerDueAlert
    {
        return CustomerDueAlert::query()
            ->where('customer_id', $customerId)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->active()
            ->latest('id')
            ->first();
    }

    public function syncFromDueSale(
        int $customerId,
        ?int $branchId,
        float $dueAmount,
        ?string $dueGivenDate,
        ?string $dueAlertAction = null,
    ): void {
        if ($dueAmount <= 0 || blank($dueGivenDate)) {
            return;
        }

        $existingAlert = $this->findActiveAlert($customerId, $branchId);

        if ($existingAlert !== null && $dueAlertAction === 'merge') {
            $newDate = Carbon::parse($dueGivenDate);
            $dateChanged = ! $existingAlert->due_given_date->isSameDay($newDate);

            $existingAlert->update([
                'due_given_date' => $dueGivenDate,
                'status' => $dateChanged
                    ? CustomerDueAlertStatus::DateChanged
                    : $existingAlert->status,
            ]);

            return;
        }

        CustomerDueAlert::create([
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'due_given_date' => $dueGivenDate,
            'status' => CustomerDueAlertStatus::Unpaid,
        ]);
    }
}
