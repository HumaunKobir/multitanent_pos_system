<?php

namespace App\Support;

use App\Models\User;

class AdminNavigation
{
    /**
     * Build the sidebar navigation tree for the given user.
     *
     * @return list<array<string, mixed>>
     */
    public function build(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $sections = [];

        foreach (config('admin-navigation.sections', []) as $section) {
            $sections[] = $this->formatSection($section);
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    protected function formatSection(array $section): array
    {
        if (isset($section['children'])) {
            return [
                'title' => $section['title'],
                'icon' => $section['icon'],
                'single' => false,
                'children' => array_map(
                    fn (array $child): array => [
                        'title' => $child['title'],
                        'href' => $child['href'] ?? null,
                    ],
                    $section['children'],
                ),
            ];
        }

        return [
            'title' => $section['title'],
            'icon' => $section['icon'],
            'href' => $section['href'] ?? null,
            'single' => (bool) ($section['single'] ?? false),
        ];
    }
}
