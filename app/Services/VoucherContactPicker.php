<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Party;
use App\Models\Supplier;

class VoucherContactPicker
{
    /**
     * @return array{
     *     parties: array<int, array{id: int, name: string}>,
     *     suppliers: array<int, array{id: int, name: string}>,
     *     customers: array<int, array{id: int, name: string}>
     * }
     */
    public static function contacts(): array
    {
        return [
            'parties' => Party::query()
                ->ownBranch()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'suppliers' => Supplier::query()
                ->ownBranch()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'customers' => Customer::query()
                ->ownBranch()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ];
    }

    public static function partyKey(?string $partyType, ?int $partyId): ?string
    {
        if (! $partyType || ! $partyId) {
            return null;
        }

        return match ($partyType) {
            Party::class => "party:{$partyId}",
            Supplier::class => "supplier:{$partyId}",
            Customer::class => "customer:{$partyId}",
            default => null,
        };
    }

    /**
     * @return array{party_type: ?string, party_id: ?int}
     */
    public static function parsePartyKey(?string $key): array
    {
        if (! $key || ! str_contains($key, ':')) {
            return ['party_type' => null, 'party_id' => null];
        }

        [$type, $id] = explode(':', $key, 2);

        $partyType = match ($type) {
            'party' => Party::class,
            'supplier' => Supplier::class,
            'customer' => Customer::class,
            default => null,
        };

        if (! $partyType || ! is_numeric($id)) {
            return ['party_type' => null, 'party_id' => null];
        }

        return ['party_type' => $partyType, 'party_id' => (int) $id];
    }
}
