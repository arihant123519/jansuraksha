<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    public function reverse(float $lat, float $lng): array
    {
        $key = config('services.google_maps.key');
        if (!$key) return ['state' => null, 'district' => null, 'taluka' => null];

        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng'      => "{$lat},{$lng}",
                'key'         => $key,
                'result_type' => 'administrative_area_level_1|administrative_area_level_2|administrative_area_level_3',
                'language'    => 'en',
            ]);

            $state = $district = $taluka = null;

            foreach ($response->json('results', []) as $result) {
                foreach ($result['address_components'] ?? [] as $c) {
                    if (in_array('administrative_area_level_1', $c['types'])) $state    = $c['long_name'];
                    if (in_array('administrative_area_level_2', $c['types'])) $district = $c['long_name'];
                    if (in_array('administrative_area_level_3', $c['types'])) $taluka   = $c['long_name'];
                }
            }

            return compact('state', 'district', 'taluka');
        } catch (\Throwable $e) {
            Log::warning("Geocode failed: " . $e->getMessage());
            return ['state' => null, 'district' => null, 'taluka' => null];
        }
    }
}