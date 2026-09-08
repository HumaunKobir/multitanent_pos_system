<?php

namespace App\Support;

use App\Models\Branch;
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
            if ($user->usesAdminPanel() && ! ($section['central_admin'] ?? false)) {
                continue;
            }

            if ($user->usesBranchPanel() && ($section['admin_only'] ?? false) && ! ($section['ecommerce_only'] ?? false)) {
                continue;
            }

            if ($user->usesBranchPanel() && ($section['ecommerce_only'] ?? false) && ! $user->canAccessEcommercePanel()) {
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
                fn (array $child): bool => $this->userCanSee($user, $child['permission'] ?? null)
                    && $this->userCanSeeBranchScope($user, $child)
                    && $this->userCanSeeEcommerceScope($user, $child),
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
                        'permission' => $child['permission'] ?? null,
                    ],
                    $children,
                ),
            ];
        }

        if (! $this->userCanSee($user, $section['permission'] ?? null)) {
            return null;
        }

        if (! $this->userCanSeeBranchScope($user, $section)) {
            return null;
        }

        if (! $this->userCanSeeEcommerceScope($user, $section)) {
            return null;
        }

        return [
            'title' => $section['title'],
            'icon' => $section['icon'],
            'href' => $this->resolveHref($section, $user),
            'single' => (bool) ($section['single'] ?? false),
            'permission' => $section['permission'] ?? null,
        ];
    }

    protected function userCanSee(User $user, ?string $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        return $user->can($permission);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function userCanSeeEcommerceScope(User $user, array $item): bool
    {
        if (! ($item['ecommerce_only'] ?? false)) {
            return true;
        }

        if ($user->usesAdminPanel()) {
            return true;
        }

        return $user->canAccessEcommercePanel();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function userCanSeeBranchScope(User $user, array $item): bool
    {
        if ($item['primary_admin_only'] ?? false) {
            return $user->bypassesPermissionChecks();
        }

        if ($item['admin_panel_only'] ?? false) {
            return $user->usesAdminPanel();
        }

        if ($item['branch_only'] ?? false) {
            return $user->usesBranchPanel();
        }

        if ($item['main_branch_only'] ?? false) {
            return Branch::isMainBranch($user->branch_id) || $user->isSuperAdmin();
        }

        if ($item['branch_received_only'] ?? false) {
            return $user->usesBranchPanel();
        }

        return true;
    }

    protected function resolveHref(array $section, User $user): ?string
    {
        if ($section['title'] === 'Dashboard') {
            return $user->usesBranchPanel()
                ? route('branch-panel.dashboard')
                : route('dashboard');
        }

        return $section['href'] ?? null;
    }
}
