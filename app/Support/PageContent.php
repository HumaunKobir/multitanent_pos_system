<?php

namespace App\Support;

use App\Models\ConfigDictionary;
use InvalidArgumentException;

final class PageContent
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function definitions(): array
    {
        return [
            'about-us' => [
                'key' => 'about_us',
                'title' => 'About Us',
                'subtitle' => 'Style, quality, and service — everything you need in one trusted store.',
                'section_title' => 'Our story',
                'default_content' => 'We bring you quality products, reliable delivery, and a shopping experience built on trust. Every order is handled with care — from selection to your doorstep.',
            ],
            'refund-policy' => [
                'key' => 'refund_policy',
                'title' => 'Refund Policy',
                'subtitle' => 'Learn how refunds are processed for your orders.',
                'section_title' => 'Refund details',
                'default_content' => 'We want you to be completely satisfied with your purchase. If you are not happy with your order, please contact us within the specified return window and we will guide you through the refund process.',
            ],
            'cancellation-policy' => [
                'key' => 'cancel_policy',
                'title' => 'Cancellation Policy',
                'subtitle' => 'Understand when and how orders can be cancelled.',
                'section_title' => 'Cancellation details',
                'default_content' => 'Orders may be cancelled before they are shipped. Once an order has been dispatched, cancellation may no longer be possible and a return process may apply instead.',
            ],
            'privacy-policy' => [
                'key' => 'privacy_policy',
                'title' => 'Privacy Policy',
                'subtitle' => 'How we collect, use, and protect your personal information.',
                'section_title' => 'Privacy details',
                'default_content' => 'We respect your privacy and are committed to protecting your personal data. This policy explains what information we collect, how we use it, and the choices you have regarding your information.',
            ],
            'terms-policy' => [
                'key' => 'terms_of_service',
                'title' => 'Terms of Service',
                'subtitle' => 'The terms and conditions for using our store and services.',
                'section_title' => 'Terms details',
                'default_content' => 'By accessing and using this website, you agree to comply with these terms of service. Please read them carefully before placing an order or using our services.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, string>
     */
    public static function definition(string $slug): array
    {
        $definition = self::definitions()[$slug] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown page content slug [{$slug}].");
        }

        return $definition;
    }

    public static function get(string $slug): string
    {
        $definition = self::definition($slug);

        return (string) ConfigDictionary::get($definition['key'], '');
    }

    public static function set(string $slug, string $content): void
    {
        $definition = self::definition($slug);

        ConfigDictionary::set($definition['key'], $content);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(string $slug, ?string $heroImage = null): array
    {
        $definition = self::definition($slug);
        $content = self::get($slug);

        return [
            'title' => $definition['title'],
            'subtitle' => $definition['subtitle'],
            'sectionTitle' => $definition['section_title'],
            'content' => $content,
            'defaultContent' => $definition['default_content'],
            'heroImage' => $heroImage,
            'showExtras' => $slug === 'about-us',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAdmin(string $slug): array
    {
        $definition = self::definition($slug);

        return [
            'slug' => $slug,
            'title' => $definition['title'],
            'content' => self::get($slug),
        ];
    }
}
