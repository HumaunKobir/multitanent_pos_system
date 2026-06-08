<?php

namespace App\Support;

use App\Models\ConfigDictionary;
use App\Models\Faq;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class FaqContent
{
    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function items(): array
    {
        $dbItems = Faq::query()
            ->active()
            ->ordered()
            ->get(['question', 'answer'])
            ->map(static fn (Faq $faq): array => [
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])
            ->all();

        if ($dbItems !== []) {
            return $dbItems;
        }

        $html = (string) ConfigDictionary::get('faq', '');
        $parsed = self::parse($html);

        return $parsed !== [] ? $parsed : self::defaults();
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function parse(string $html): array
    {
        $html = trim($html);

        if ($html === '') {
            return [];
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $wrapper = $document->getElementsByTagName('div')->item(0);

        if (! $wrapper instanceof DOMElement) {
            return [];
        }

        $detailsItems = self::parseDetails($wrapper);

        if ($detailsItems !== []) {
            return $detailsItems;
        }

        return self::parseHeadings($wrapper);
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private static function parseDetails(DOMElement $wrapper): array
    {
        $items = [];

        foreach ($wrapper->getElementsByTagName('details') as $details) {
            if (! $details instanceof DOMElement) {
                continue;
            }

            $summary = null;

            foreach ($details->childNodes as $child) {
                if ($child instanceof DOMElement && $child->nodeName === 'summary') {
                    $summary = $child;
                    break;
                }
            }

            if (! $summary instanceof DOMElement) {
                continue;
            }

            $question = trim($summary->textContent ?? '');

            if ($question === '') {
                continue;
            }

            $answer = self::innerHtmlWithout($details, $summary);

            if ($answer !== '') {
                $items[] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private static function parseHeadings(DOMElement $wrapper): array
    {
        $xpath = new DOMXPath($wrapper->ownerDocument ?? new DOMDocument);
        $headings = $xpath->query('.//h2|.//h3|.//h4', $wrapper);

        if ($headings === false) {
            return [];
        }

        $items = [];

        foreach ($headings as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            $question = trim($heading->textContent ?? '');

            if ($question === '') {
                continue;
            }

            $answerNodes = [];
            $node = $heading->nextSibling;

            while ($node !== null) {
                if ($node instanceof DOMElement && in_array($node->nodeName, ['h2', 'h3', 'h4'], true)) {
                    break;
                }

                if ($node instanceof DOMElement) {
                    $answerNodes[] = $node;
                }

                $node = $node->nextSibling;
            }

            if ($answerNodes === []) {
                continue;
            }

            $answer = implode('', array_map(
                static fn (DOMElement $element): string => $element->ownerDocument?->saveHTML($element) ?? '',
                $answerNodes,
            ));

            $items[] = [
                'question' => $question,
                'answer' => trim($answer),
            ];
        }

        return $items;
    }

    private static function innerHtmlWithout(DOMElement $parent, DOMElement $exclude): string
    {
        $html = '';

        foreach ($parent->childNodes as $child) {
            if ($child->isSameNode($exclude)) {
                continue;
            }

            $html .= $parent->ownerDocument?->saveHTML($child) ?? '';
        }

        return trim($html);
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public static function defaults(): array
    {
        return [
            [
                'question' => 'How do I place an order?',
                'answer' => '<p>Browse our products, add items to your cart, and proceed to checkout. Enter your delivery details, choose a payment method, and confirm your order. You will receive an order confirmation once it is placed.</p>',
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => '<p>We accept online payments through SSLCommerz as well as Cash on Delivery (COD) where available. Available options are shown at checkout.</p>',
            ],
            [
                'question' => 'How long does delivery take?',
                'answer' => '<p>Delivery times depend on your location. Orders inside Dhaka are typically delivered faster than outside Dhaka. You will receive updates as your order is processed and shipped.</p>',
            ],
            [
                'question' => 'Can I cancel my order?',
                'answer' => '<p>Orders can usually be cancelled before they are shipped. Once dispatched, cancellation may no longer be possible and a return process may apply instead. See our <a href="/cancellation-policy">Cancellation Policy</a> for details.</p>',
            ],
            [
                'question' => 'How do returns and refunds work?',
                'answer' => '<p>If you are not satisfied with your purchase, contact us within the return window outlined in our <a href="/refund-policy">Refund Policy</a>. Our team will guide you through the return and refund process.</p>',
            ],
            [
                'question' => 'How can I track my order?',
                'answer' => '<p>Log in to your customer account and visit <strong>My Orders</strong> to view order status and tracking details. You can also contact our support team with your order number for assistance.</p>',
            ],
        ];
    }
}
