<?php

namespace App\Support;

use App\Models\ConfigDictionary;

final class MailSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'smtp_enabled' => '0',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'mail_from_address' => '',
            'mail_from_name' => '',
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

        return ConfigDictionary::get($key, $defaults[$key] ?? $default);
    }

    public static function isConfigured(): bool
    {
        if (! filter_var(self::get('smtp_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return filled(self::get('smtp_host'))
            && filled(self::get('smtp_port'))
            && filled(self::get('mail_from_address'));
    }

    public static function hasPassword(): bool
    {
        return filled(self::get('smtp_password'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAdmin(): array
    {
        $settings = [];

        foreach (self::defaults() as $key => $default) {
            $settings[$key] = self::get($key, $default);
        }

        unset($settings['smtp_password']);
        $settings['smtp_password_configured'] = self::hasPassword();

        return $settings;
    }
}
