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

            $formatted = $this->formatSection($section, $user);

            if ($formatted === null) {
                continue;
            }

            $sections[] = $formatted;
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>|null
     */
    protected function formatSection(array $section, User $user): ?array
    {
        if (isset($section['children'])) {
            $children = array_values(array_filter(
                $section['children'],
                fn (array $child): bool => $this->userCanSee($user, $child['permission'] ?? null),
            ));

            if (empty($children)) {
                return null;
            }

            return [
                'title' => $section['title'],
                'icon' => $section['icon'],
                'single' => false,
                'children' => array_map(
                    fn (array $child): array => [
                        'title' => $child['title'],
                        'href' => $child['href'] ?? null,
                    ],
                    $children,
                ),
            ];
        }

        if (! $this->userCanSee($user, $section['permission'] ?? null)) {
            return null;
        }

        return [
            'title' => $section['title'],
            'icon' => $section['icon'],
            'href' => $this->resolveHref($section, $user),
            'single' => (bool) ($section['single'] ?? false),
        ];
    }

    protected function userCanSee(User $user, ?string $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        return $user->can($permission);
    }

    protected function resolveHref(array $section, User $user): ?string
    {
        if ($section['title'] === 'Dashboard') {
            return $user->isBranchUser()
                ? route('branch-panel.dashboard')
                : route('dashboard');
        }

        return $section['href'] ?? null;
    }
}
