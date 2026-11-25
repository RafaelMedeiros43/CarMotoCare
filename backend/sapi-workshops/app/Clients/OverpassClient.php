<?php

declare(strict_types=1);

namespace App\Clients;

use App\Exceptions\OverpassUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class OverpassClient
{
    private const TYPE_OFICINA = 'OFICINA';
    private const TYPE_LOJA_PNEUS = 'LOJA_PNEUS';

    /**
     * Mapeamento das tags OSM `shop=*` para o tipo da API.
     *
     * @var array<string, string>
     */
    private const SHOP_TYPE_MAP = [
        'car_repair' => self::TYPE_OFICINA,
        'motorcycle_repair' => self::TYPE_OFICINA,
        'tyres' => self::TYPE_LOJA_PNEUS,
    ];

    /**
     * Pesquisa oficinas e lojas de pneus à volta de um ponto e devolve-as já normalizadas.
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     address: string|null,
     *     latitude: float,
     *     longitude: float,
     *     services: array<int, string>|null
     * }>
     *
     * @throws OverpassUnavailableException
     */
    public function search(float $lat, float $lon, float $radiusKm): array
    {
        $url = (string) config('services.overpass.url');
        $timeout = max(1, (int) config('services.overpass.timeout', 10));

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->post($url, [
                    'data' => $this->buildQuery($lat, $lon, $radiusKm, $timeout),
                ]);
        } catch (ConnectionException $e) {
            throw new OverpassUnavailableException(
                'Não foi possível contactar a Overpass API (falha de ligação ou timeout).',
                0,
                $e,
            );
        }

        if (! $response->successful()) {
            throw new OverpassUnavailableException(
                sprintf('A Overpass API respondeu com o estado HTTP %d.', $response->status()),
            );
        }

        $json = $response->json();

        if (! is_array($json) || ! isset($json['elements']) || ! is_array($json['elements'])) {
            throw new OverpassUnavailableException('A Overpass API devolveu uma resposta inválida.');
        }

        if ($json['elements'] === [] && isset($json['remark']) && is_string($json['remark'])
            && (str_contains($json['remark'], 'runtime error') || str_contains($json['remark'], 'timed out'))) {
            throw new OverpassUnavailableException('A Overpass API não conseguiu concluir a pesquisa.');
        }

        $workshops = [];

        foreach ($json['elements'] as $element) {
            if (! is_array($element)) {
                continue;
            }

            $workshop = $this->normalizeElement($element);

            if ($workshop !== null) {
                $workshops[] = $workshop;
            }
        }

        return $workshops;
    }

    private function buildQuery(float $lat, float $lon, float $radiusKm, int $timeout): string
    {
        $radiusMeters = (int) round($radiusKm * 1000);
        $around = sprintf('around:%d,%s,%s', $radiusMeters, $this->coordinate($lat), $this->coordinate($lon));
        $filter = '["shop"~"^(car_repair|motorcycle_repair|tyres)$"]';

        return sprintf(
            "[out:json][timeout:%d];\n(\n  node%s(%s);\n  way%s(%s);\n);\nout center;",
            $timeout,
            $filter,
            $around,
            $filter,
            $around,
        );
    }

    private function coordinate(float $value): string
    {
        return sprintf('%.6F', $value);
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     address: string|null,
     *     latitude: float,
     *     longitude: float,
     *     services: array<int, string>|null
     * }|null
     */
    private function normalizeElement(array $element): ?array
    {
        $osmType = $element['type'] ?? null;
        $osmId = $element['id'] ?? null;

        if (! in_array($osmType, ['node', 'way'], true) || ! is_int($osmId)) {
            return null;
        }

        $tags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : [];
        $shop = $tags['shop'] ?? null;

        if (! is_string($shop) || ! isset(self::SHOP_TYPE_MAP[$shop])) {
            return null;
        }

        $coordinates = $this->extractCoordinates($element);

        if ($coordinates === null) {
            return null;
        }

        return [
            'id' => $osmType.'/'.$osmId,
            'name' => $this->resolveName($tags),
            'type' => self::SHOP_TYPE_MAP[$shop],
            'address' => $this->buildAddress($tags),
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lon'],
            'services' => $this->extractServices($tags),
        ];
    }

    /**
     * Nos `node` usa lat/lon; nos `way` usa o `center` (out center;).
     *
     * @param  array<string, mixed>  $element
     * @return array{lat: float, lon: float}|null
     */
    private function extractCoordinates(array $element): ?array
    {
        if (isset($element['lat'], $element['lon']) && is_numeric($element['lat']) && is_numeric($element['lon'])) {
            return ['lat' => (float) $element['lat'], 'lon' => (float) $element['lon']];
        }

        $center = $element['center'] ?? null;

        if (is_array($center) && isset($center['lat'], $center['lon'])
            && is_numeric($center['lat']) && is_numeric($center['lon'])) {
            return ['lat' => (float) $center['lat'], 'lon' => (float) $center['lon']];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function resolveName(array $tags): string
    {
        foreach (['name', 'brand', 'operator'] as $key) {
            $value = $tags[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'Sem nome';
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function buildAddress(array $tags): ?string
    {
        $street = $this->tagString($tags, 'addr:street');
        $number = $this->tagString($tags, 'addr:housenumber');
        $postcode = $this->tagString($tags, 'addr:postcode');
        $city = $this->tagString($tags, 'addr:city');

        $streetLine = trim(implode(' ', array_filter([$street, $number])));
        $cityLine = trim(implode(' ', array_filter([$postcode, $city])));

        $address = implode(', ', array_filter([$streetLine, $cityLine]));

        return $address !== '' ? $address : null;
    }

    /**
     * Serviços derivados das tags `service:vehicle:*=yes`.
     *
     * @param  array<string, mixed>  $tags
     * @return array<int, string>|null
     */
    private function extractServices(array $tags): ?array
    {
        $services = [];

        foreach ($tags as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'service:vehicle:') && $value === 'yes') {
                $services[] = str_replace('_', ' ', substr($key, strlen('service:vehicle:')));
            }
        }

        return $services !== [] ? array_values($services) : null;
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function tagString(array $tags, string $key): ?string
    {
        $value = $tags[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
