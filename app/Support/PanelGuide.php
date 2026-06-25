<?php

namespace App\Support;

use App\Models\User;

class PanelGuide
{
    public function __construct(public AdminNavigation $navigation) {}

    /**
     * Build permission-filtered panel guide sections for the given user.
     *
     * @return array{sections: list<array<string, mixed>>, panelType: string}
     */
    public function build(?User $user): array
    {
        if ($user === null) {
            return ['sections' => [], 'panelType' => 'admin'];
        }

        $panelType = $user->usesBranchPanel() ? 'branch' : 'admin';
        $entries = config('panel-guide.entries', []);
        $sections = [];

        foreach ($this->navigation->build($user) as $navSection) {
            $items = [];

            if (! empty($navSection['children'])) {
                $seenTitles = [];

                foreach ($navSection['children'] as $child) {
                    $guide = $this->resolveEntry($entries, $child, $panelType);

                    if ($guide === null) {
                        continue;
                    }

                    $dedupeKey = $guide['title'];

                    if (isset($seenTitles[$dedupeKey])) {
                        continue;
                    }

                    $seenTitles[$dedupeKey] = true;

                    $items[] = array_merge($guide, [
                        'href' => $child['href'] ?? null,
                        'permission' => $child['permission'] ?? null,
                    ]);
                }
            } elseif ($navSection['single'] ?? false) {
                $guide = $this->resolveEntry($entries, $navSection, $panelType);

                if ($guide !== null) {
                    $items[] = array_merge($guide, [
                        'href' => $navSection['href'] ?? null,
                        'permission' => $navSection['permission'] ?? null,
                    ]);
                }
            }

            if ($items === []) {
                continue;
            }

            $sections[] = [
                'title' => $navSection['title'],
                'icon' => $navSection['icon'] ?? null,
                'items' => $items,
            ];
        }

        return [
            'sections' => $sections,
            'panelType' => $panelType,
        ];
    }

    /**
     * @param  array<string, mixed>  $entries
     * @param  array<string, mixed>  $navItem
     * @return array<string, mixed>|null
     */
    protected function resolveEntry(array $entries, array $navItem, string $panelType): ?array
    {
        $key = $this->entryKey($navItem);
        $entry = $entries[$key] ?? null;

        if ($entry === null) {
            return null;
        }

        if (array_key_exists($panelType, $entry) && $entry[$panelType] === null) {
            return null;
        }

        $panelOverride = is_array($entry[$panelType] ?? null) ? $entry[$panelType] : [];

        $merged = array_merge($entry, $panelOverride);
        unset($merged['admin'], $merged['branch']);

        return [
            'title' => $merged['title'] ?? $navItem['title'],
            'summary' => $merged['summary'] ?? '',
            'steps' => array_values($merged['steps'] ?? []),
            'tips' => array_values($merged['tips'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $navItem
     */
    protected function entryKey(array $navItem): string
    {
        if (! empty($navItem['permission'])) {
            return $navItem['permission'];
        }

        $href = $navItem['href'] ?? '';

        if (str_contains($href, 'admin-profile')) {
            return 'admin-profile';
        }

        return $href;
    }
}
