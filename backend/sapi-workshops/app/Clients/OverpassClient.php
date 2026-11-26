<?php

declare(strict_types=1);

namespace App\Clients;

use App\Exceptions\OverpassUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Servidores por ordem de preferência (o overpass-api.de é o mais congestionado).
     *
     * @var array<int, string>
     */
    private const DEFAULT_ENDPOINTS = [
        'https://overpass.private.coffee/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass-api.de/api/interpreter',
    ];

    /** Estados HTTP em que vale a pena tentar o servidor seguinte. */
    private const RETRYABLE_STATUS = [429, 500, 502, 503, 504];

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
        // Coordenadas a 2 casas (~1 km) para pesquisas próximas partilharem a cache.
        $key = sprintf('overpass:%.2f:%.2f:%.0f', $lat, $lon, $radiusKm);

        // Só guarda se a Overpass responder; se der erro, nada fica em cache.
        return Cache::remember(
            $key,
            now()->addHours(24),
            fn (): array => $this->fetch($lat, $lon, $radiusKm),
        );
    }

    /**
     * Faz o pedido real à Overpass API (sem cache), com fallback entre servidores.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws OverpassUnavailableException
     */
    private function fetch(float $lat, float $lon, float $radiusKm): array
    {
        $timeout = max(1, (int) config('services.overpass.timeout', 10));
        $query = $this->buildQuery($lat, $lon, $radiusKm, $timeout);

        $json = null;
        $lastError = 'sem detalhe';

        foreach ($this->endpoints() as $url) {
            try {
                $response = Http::asForm()
                    ->withUserAgent('CarMotoCare/1.0 (projeto academico; contacto: 2024109911@uac.pt)')
                    ->connectTimeout(4)
                    ->timeout($timeout + 3)
                    ->post($url, ['data' => $query]);
            } catch (ConnectionException) {
                $lastError = "ligação/timeout em {$url}";
                Log::warning('Overpass: '.$lastError);
                continue;
            }

            if (in_array($response->status(), self::RETRYABLE_STATUS, true)) {
                $lastError = "HTTP {$response->status()} em {$url}";
                Log::warning('Overpass: '.$lastError);
                continue;
            }

            if (! $response->successful()) {
                throw new OverpassUnavailableException(
                    sprintf('A Overpass API respondeu com o estado HTTP %d.', $response->status()),
                );
            }

            $candidate = $response->json();

            if (! is_array($candidate) || ! isset($candidate['elements']) || ! is_array($candidate['elements'])) {
                $lastError = "resposta inválida de {$url}";
                Log::warning('Overpass: '.$lastError);
                continue;
            }

            if ($candidate['elements'] === [] && isset($candidate['remark']) && is_string($candidate['remark'])
                && (str_contains($candidate['remark'], 'runtime error') || str_contains($candidate['remark'], 'timed out'))) {
                $lastError = "pesquisa não concluída em {$url}";
                Log::warning('Overpass: '.$lastError);
                continue;
            }

            $json = $candidate;
            break;
        }

        if ($json === null) {
            throw new OverpassUnavailableException(
                'Serviço de pesquisa de oficinas temporariamente indisponível ('.$lastError.').',
            );
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

    /**
     * @return array<int, string>
     */
    private function endpoints(): array
    {
        $configured = config('services.overpass.urls');

        if (is_array($configured) && $configured !== []) {
            return array_values($configured);
        }

        $single = config('services.overpass.url');

        return is_string($single) && $single !== ''
            ? array_values(array_unique([$single, ...self::DEFAULT_ENDPOINTS]))
            : self::DEFAULT_ENDPOINTS;
    }

    private function buildQuery(float $lat, float $lon, float $radiusKm, int $timeout): string
    {
        $radiusMeters = (int) round($radiusKm * 1000);
        $around = sprintf('around:%d,%s,%s', $radiusMeters, $this->coordinate($lat), $this->coordinate($lon));

        // Tags exatas (sem regex) e uma só linha `nwr` por tag.
        $lines = [];
        foreach (['car_repair', 'motorcycle_repair', 'tyres'] as $shop) {
            $lines[] = sprintf('  nwr["shop"="%s"](%s);', $shop, $around);
        }

        return sprintf(
            "[out:json][timeout:%d][maxsize:16777216];\n(\n%s\n);\nout center 100;",
            $timeout,
            implode("\n", $lines),
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
