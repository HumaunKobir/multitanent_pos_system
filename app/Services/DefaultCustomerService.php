<?php

namespace App\Services;

use App\Enums\CommonStatus;
use App\Enums\CustomerRegistrationType;
use App\Models\Branch;
use App\Models\Customer;

class DefaultCustomerService
{
    public const string WALK_IN_CUSTOMER_NAME = 'Walk-in Customer';

    public static function seed(Branch $branch): Customer
    {
        $existing = Customer::query()
            ->where('branch_id', $branch->id)
            ->where('is_default', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => self::WALK_IN_CUSTOMER_NAME,
            'phone' => $branch->phone,
            'password' => '12345678',
            'status' => CommonStatus::Active,
            'is_default' => true,
            'registration_type' => CustomerRegistrationType::Offline,
        ]);
    }
}
