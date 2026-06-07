<?php

namespace App\Support;

use App\Models\ConfigDictionary;

final class WebsiteSettings
{
    public const DEFAULT_FOOTER_DESCRIPTION = 'Your destination for fashion and apparel — quality products, reliable delivery, and a shopping experience built on trust.';

    public const DEFAULT_SUPPORT_TIME = 'Sat–Thu, 10AM–8PM';

    /**
     * @return array<string, mixed>
     */
    public static function textDefaults(): array
    {
        return [
            'website_name' => config('app.name', 'Coolness Point'),
            'phone' => '',
            'email' => '',
            'address' => '',
            'fb_share_for_withdraw' => '',
            'youtube' => '',
            'twit' => '',
            'linkend' => '',
            'topnotice1' => '',
            'footer_description' => self::DEFAULT_FOOTER_DESCRIPTION,
            'support_time' => self::DEFAULT_SUPPORT_TIME,
            'delivery_charge_inside_dhaka' => '60',
            'delivery_charge_outside_dhaka' => '120',
            'online_sslcommerz_payment_account_id' => '',
            'online_cod_payment_account_id' => '',
            'meta_tags' => '',
            'meta_description' => '',
            'newsletter_enabled' => '1',
            'newsletter_title' => 'Sign Up For Newsletter',
            'newsletter_description' => '',
            'newsletter_placeholder' => 'Your Email Address...',
            'newsletter_button' => 'Subscribe',
        ];
    }

    /**
     * @return list<string>
     */
    public static function textKeys(): array
    {
        return array_keys(self::textDefaults());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $defaults = self::textDefaults();

        if ($key === 'footer_description') {
            return ConfigDictionary::get('footer_description')
                ?? ConfigDictionary::get('description')
                ?? ($default ?? self::DEFAULT_FOOTER_DESCRIPTION);
        }

        $fallback = $defaults[$key] ?? $default;

        return ConfigDictionary::get($key, $fallback);
    }

    public static function deliveryChargeForCity(?int $cityId): float
    {
        $insideDhaka = (float) self::get('delivery_charge_inside_dhaka', '60');
        $outsideDhaka = (float) self::get('delivery_charge_outside_dhaka', '120');

        if ($cityId === null) {
            return $insideDhaka;
        }

        return $cityId === 1 ? $insideDhaka : $outsideDhaka;
    }

    public static function onlineSslCommerzPaymentAccountId(): ?int
    {
        return self::resolvePaymentAccountId('online_sslcommerz_payment_account_id', 'ONLINE_SSLCOMMERZ_PAYMENT_ACCOUNT_ID');
    }

    public static function onlineCodPaymentAccountId(): ?int
    {
        return self::resolvePaymentAccountId('online_cod_payment_account_id', 'ONLINE_COD_PAYMENT_ACCOUNT_ID');
    }

    private static function resolvePaymentAccountId(string $settingKey, string $envKey): ?int
    {
        $value = self::get($settingKey);

        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        $envValue = env($envKey);

        if ($envValue !== null && $envValue !== '') {
            return (int) $envValue;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAdmin(): array
    {
        $settings = [];

        foreach (self::textDefaults() as $key => $default) {
            $settings[$key] = self::get($key, $default);
        }

        $settings['logo'] = ConfigDictionary::get('logo');
        $settings['fav_icon'] = ConfigDictionary::get('fav_icon');
        $settings['logo_url'] = StorageUrl::public($settings['logo']);
        $settings['fav_icon_url'] = StorageUrl::public($settings['fav_icon']);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        return [
            'logo' => StorageUrl::public(ConfigDictionary::get('logo')),
            'favicon' => StorageUrl::public(ConfigDictionary::get('fav_icon')),
            'siteName' => self::get('website_name'),
            'topNotice' => self::get('topnotice1'),
            'footerDescription' => self::get('footer_description'),
            'supportTime' => self::get('support_time'),
            'contact' => [
                'phone' => self::get('phone'),
                'email' => self::get('email'),
                'address' => self::get('address'),
            ],
            'social' => [
                'facebook' => self::get('fb_share_for_withdraw'),
                'youtube' => self::get('youtube'),
                'twitter' => self::get('twit'),
                'linkedin' => self::get('linkend'),
            ],
            'deliveryCharges' => [
                'inside_dhaka' => (float) self::get('delivery_charge_inside_dhaka', '60'),
                'outside_dhaka' => (float) self::get('delivery_charge_outside_dhaka', '120'),
            ],
            'newsletter' => [
                'enabled' => filter_var(self::get('newsletter_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
                'title' => self::get('newsletter_title', 'Sign Up For Newsletter'),
                'description' => self::get('newsletter_description', ''),
                'placeholder' => self::get('newsletter_placeholder', 'Your Email Address...'),
                'button' => self::get('newsletter_button', 'Subscribe'),
            ],
            'seo' => [
                'meta_tags' => self::get('meta_tags'),
                'meta_description' => self::get('meta_description'),
            ],
        ];
    }
}
