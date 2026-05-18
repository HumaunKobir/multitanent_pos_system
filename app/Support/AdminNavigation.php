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
            if ($user->isBranchUser() && ($section['admin_only'] ?? false)) {
                continue;
            }

            if (! $user->isBranchUser() && ($section['branch_only'] ?? false)) {
                continue;
            }

            $sections[] = $this->formatSection($section, $user);
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    protected function formatSection(array $section, User $user): array
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
            'href' => $this->resolveHref($section, $user),
            'single' => (bool) ($section['single'] ?? false),
        ];
    }

    protected function resolveHref(array $section, User $user): ?string
    {
        if ($section['title'] === 'Dashboard') {
            return $user->isBranchUser()
                ? '/branch-panel'
                : '/admin';
        }

        return $section['href'] ?? null;
    }
}
