<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PathaoCourierController extends Controller
{
    /**
     * Stub endpoints for Phase 12 Pathao integration.
     * Replace with codeboxr/pathao-courier when package is installed.
     */
    public function getCities(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['city_id' => 1, 'city_name' => 'Dhaka'],
                ['city_id' => 2, 'city_name' => 'Outside Dhaka'],
            ],
        ]);
    }

    public function getZones(int $cityId): JsonResponse
    {
        return response()->json([
            'data' => $cityId === 1
                ? [['zone_id' => 1, 'zone_name' => 'Dhaka Metro']]
                : [['zone_id' => 2, 'zone_name' => 'Other Zone']],
        ]);
    }

    public function getAreas(int $zoneId): JsonResponse
    {
        return response()->json([
            'data' => [
                ['area_id' => 1, 'area_name' => 'Area 1'],
                ['area_id' => 2, 'area_name' => 'Area 2'],
            ],
        ]);
    }
}
