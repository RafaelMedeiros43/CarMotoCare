<?php

declare(strict_types=1);

namespace App\Services;

use App\Clients\OverpassClient;
use App\Exceptions\OverpassUnavailableException;

final class WorkshopService
{
    private const EARTH_RADIUS_KM = 6371.0088;

    public function __construct(private readonly OverpassClient $overpass)
    {
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     address: string|null,
     *     latitude: float,
     *     longitude: float,
     *     distanceKm: float,
     *     services: array<int, string>|null
     * }>
     *
     * @throws OverpassUnavailableException
     */
    public function search(float $lat, float $lon, float $radiusKm, ?string $type, ?string $query): array
    {
        $needle = $query !== null ? mb_strtolower(trim($query)) : '';

        $results = [];

        foreach ($this->overpass->search($lat, $lon, $radiusKm) as $workshop) {
            if ($type !== null && $workshop['type'] !== $type) {
                continue;
            }

            if ($needle !== '' && ! $this->matchesQuery($workshop, $needle)) {
                continue;
            }

            $distance = $this->haversineKm($lat, $lon, $workshop['latitude'], $workshop['longitude']);

            if ($distance > $radiusKm) {
                continue;
            }

            $results[] = ['distance' => $distance, 'workshop' => $workshop];
        }

        usort($results, static function (array $a, array $b): int {
            return [$a['distance'], $a['workshop']['id']] <=> [$b['distance'], $b['workshop']['id']];
        });

        return array_map(static function (array $item): array {
            $workshop = $item['workshop'];

            return [
                'id' => $workshop['id'],
                'name' => $workshop['name'],
                'type' => $workshop['type'],
                'address' => $workshop['address'],
                'latitude' => $workshop['latitude'],
                'longitude' => $workshop['longitude'],
                'distanceKm' => round($item['distance'], 1),
                'services' => $workshop['services'],
            ];
        }, $results);
    }

    /**
     * @param  array{name: string, services: array<int, string>|null}  $workshop
     */
    private function matchesQuery(array $workshop, string $needle): bool
    {
        if (str_contains(mb_strtolower($workshop['name']), $needle)) {
            return true;
        }

        foreach ($workshop['services'] ?? [] as $service) {
            if (str_contains(mb_strtolower($service), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
