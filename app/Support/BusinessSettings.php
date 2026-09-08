<?php

namespace App\Support;

use App\Models\BusinessSetting;

final class BusinessSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            // Subscription & Billing Settings
            'subscription_billing_cycle' => 'monthly',
            'subscription_billing_cycle_days' => '30',
            'subscription_payment_window_days' => '7',
            'subscription_warning_days' => '5',
            'subscription_grace_period_days' => '7',
            'subscription_overdue_action' => 'restrict_sales',
            'subscription_default_fee' => '1500',
            'subscription_payment_instructions' => "Please send subscription payment via:\n- Bank: City Bank (A/C: 123456789, Branch: Banani)\n- bKash / Nagad (Merchant/Personal): +8801700000000\nAfter payment, send transaction ID to Superadmin.",
            'subscription_warning_message' => 'Your branch subscription will expire in {days_left} days (Due: {due_date}). Please pay {fee_amount} to prevent service disruption.',
            'subscription_policy_terms' => "1. All branch subscriptions are prepaid according to the chosen billing cycle.\n2. Invoices are generated at the start of each billing cycle with a payment window.\n3. Warning notices will be displayed before the due date.\n4. If payment is not cleared within the grace period, sales/POS access will be restricted until renewal.",

            // Superadmin Contact Info
            'superadmin_contact_name' => 'System SuperAdmin',
            'superadmin_contact_phone' => '+8801700000000',
            'superadmin_contact_email' => 'admin@coolness.com',

            // System Rules & Policies
            'policy_allow_negative_stock' => '0',
            'policy_max_discount_percent' => '50',
            'policy_require_daily_business_session' => '1',
            'policy_require_customer_phone' => '0',
            'policy_max_users_per_branch' => '10',
            'policy_max_products_per_branch' => '1000',

            // Feature Policies for Client Branches
            'feature_ecommerce_enabled' => '1',
            'feature_loyalty_coins_enabled' => '1',
            'feature_accounts_vouchers_enabled' => '1',
            'feature_stock_distribution_enabled' => '1',
            'feature_damage_tracking_enabled' => '1',
            'feature_special_discounts_enabled' => '1',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::defaults());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $defaults = self::defaults();
        $fallback = $defaults[$key] ?? $default;

        return BusinessSetting::get($key, $fallback);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key, $default ? '1' : '0');

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function getFloat(string $key, float $default = 0.0): float
    {
        return (float) self::get($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $defaults = self::defaults();
        $results = [];

        foreach ($defaults as $key => $defaultVal) {
            $results[$key] = BusinessSetting::get($key, $defaultVal);
        }

        return $results;
    }
}
