<?php

namespace App\Support;

final class DynamicMailConfigurator
{
    public static function apply(): void
    {
        if (! MailSettings::isConfigured()) {
            return;
        }

        $encryption = (string) MailSettings::get('smtp_encryption', 'tls');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => (string) MailSettings::get('smtp_host'),
            'mail.mailers.smtp.port' => (int) MailSettings::get('smtp_port', 587),
            'mail.mailers.smtp.username' => (string) MailSettings::get('smtp_username'),
            'mail.mailers.smtp.password' => (string) MailSettings::get('smtp_password'),
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.from.address' => (string) MailSettings::get('mail_from_address'),
            'mail.from.name' => (string) (MailSettings::get('mail_from_name') ?: WebsiteSettings::get('website_name')),
        ]);
    }
}
