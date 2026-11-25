<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class WorkshopsTest extends TestCase
{
    private const LAT = 38.7223;
    private const LON = -9.1393;

    /**
     * @return array<string, mixed>
     */
    private function overpassPayload(): array
    {
        return [
            'version' => 0.6,
            'elements' => [
                // Mais distante (~6.3 km), propositadamente listado primeiro.
                [
                    'type' => 'node',
                    'id' => 3,
                    'lat' => 38.77,
                    'lon' => -9.10,
                    'tags' => [
                        'shop' => 'motorcycle_repair',
                        'name' => 'Moto Serviço',
                    ],
                ],
                // Way com `center` (~3.2 km).
                [
                    'type' => 'way',
                    'id' => 2,
                    'center' => ['lat' => 38.75, 'lon' => -9.15],
                    'tags' => [
                        'shop' => 'tyres',
                        'name' => 'Pneus Lisboa',
                        'addr:street' => 'Rua das Flores',
                        'addr:housenumber' => '12',
                        'addr:postcode' => '1200-195',
                        'addr:city' => 'Lisboa',
                        'service:vehicle:tyres' => 'yes',
                    ],
                ],
                // Mais próximo (~0.9 km).
                [
                    'type' => 'node',
                    'id' => 1,
                    'lat' => 38.73,
                    'lon' => -9.14,
                    'tags' => [
                        'shop' => 'car_repair',
                        'name' => 'Oficina Central',
                    ],
                ],
                // Tag não suportada: deve ser ignorado.
                [
                    'type' => 'node',
                    'id' => 4,
                    'lat' => 38.72,
                    'lon' => -9.13,
                    'tags' => [
                        'shop' => 'bakery',
                        'name' => 'Padaria',
                    ],
                ],
            ],
        ];
    }

    private function fakeOverpassSuccess(): void
    {
        Http::fake(['*' => Http::response($this->overpassPayload(), 200)]);
    }

    private function assertProblemJson(TestResponse $response): void
    {
        $this->assertStringContainsString(
            'application/problem+json',
            (string) $response->headers->get('Content-Type'),
        );
    }

    public function test_search_returns_nodes_and_ways_as_plain_array(): void
    {
        $this->fakeOverpassSuccess();

        $response = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON);

        $response->assertOk();
        $response->assertJsonCount(3);
        $response->assertJsonMissingPath('data');

        $response->assertJsonStructure([
            '*' => ['id', 'name', 'type', 'address', 'latitude', 'longitude', 'distanceKm', 'services'],
        ]);

        $items = $response->json();

        $this->assertSame('node/1', $items[0]['id']);
        $this->assertSame('OFICINA', $items[0]['type']);
        $this->assertNull($items[0]['address']);
        $this->assertNull($items[0]['services']);

        $this->assertSame('way/2', $items[1]['id']);
        $this->assertSame('LOJA_PNEUS', $items[1]['type']);
        $this->assertSame('Rua das Flores 12, 1200-195 Lisboa', $items[1]['address']);
        $this->assertSame(38.75, $items[1]['latitude']);
        $this->assertSame(-9.15, $items[1]['longitude']);
        $this->assertSame(['tyres'], $items[1]['services']);

        $this->assertSame('node/3', $items[2]['id']);
        $this->assertSame('OFICINA', $items[2]['type']);

        Http::assertSent(function (Request $request): bool {
            $query = $request->data()['data'] ?? '';

            return str_contains($query, 'node[')
                && str_contains($query, 'way[')
                && str_contains($query, 'out center;')
                && str_contains($query, 'around:10000,');
        });
    }

    public function test_results_are_sorted_by_distance_ascending(): void
    {
        $this->fakeOverpassSuccess();

        $items = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON)
            ->assertOk()
            ->json();

        $distances = array_column($items, 'distanceKm');

        $sorted = $distances;
        sort($sorted);

        $this->assertSame($sorted, $distances);
        $this->assertEqualsWithDelta(0.9, $distances[0], 0.15);
        $this->assertEqualsWithDelta(3.2, $distances[1], 0.15);
        $this->assertEqualsWithDelta(6.3, $distances[2], 0.15);

        foreach ($distances as $distance) {
            $this->assertEqualsWithDelta(round($distance, 1), $distance, 0.0000001);
        }
    }

    public function test_radius_filters_far_results(): void
    {
        $this->fakeOverpassSuccess();

        $items = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON.'&radiusKm=2')
            ->assertOk()
            ->json();

        $this->assertSame(['node/1'], array_column($items, 'id'));

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->data()['data'] ?? '', 'around:2000,');
        });
    }

    public function test_type_filter(): void
    {
        $this->fakeOverpassSuccess();

        $items = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON.'&type=LOJA_PNEUS')
            ->assertOk()
            ->json();

        $this->assertSame(['way/2'], array_column($items, 'id'));

        $items = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON.'&type=OFICINA')
            ->assertOk()
            ->json();

        $this->assertSame(['node/1', 'node/3'], array_column($items, 'id'));
    }

    public function test_q_filter_is_case_insensitive_and_matches_name_and_services(): void
    {
        $this->fakeOverpassSuccess();

        $byName = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON.'&q=MOTO')
            ->assertOk()
            ->json();

        $this->assertSame(['node/3'], array_column($byName, 'id'));

        $byService = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON.'&q=TYRES')
            ->assertOk()
            ->json();

        $this->assertSame(['way/2'], array_column($byService, 'id'));

        $none = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON.'&q=inexistente')
            ->assertOk()
            ->json();

        $this->assertSame([], $none);
    }

    public function test_missing_required_params_returns_400_problem_json(): void
    {
        Http::fake();

        $response = $this->getJson('/workshops');

        $response->assertStatus(400);
        $this->assertProblemJson($response);

        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Pedido inválido',
            'status' => 400,
        ]);
        $response->assertJsonStructure([
            'type', 'title', 'status', 'detail',
            'errors' => [['field', 'message']],
        ]);

        $fields = array_column($response->json('errors'), 'field');
        $this->assertContains('lat', $fields);
        $this->assertContains('lon', $fields);

        Http::assertNothingSent();
    }

    public function test_out_of_range_and_invalid_values_return_400(): void
    {
        Http::fake();

        $cases = [
            'lat' => '/workshops?lat=91&lon=0',
            'lon' => '/workshops?lat=0&lon=181',
            'radiusKm' => '/workshops?lat=0&lon=0&radiusKm=51',
            'type' => '/workshops?lat=0&lon=0&type=INVALIDO',
        ];

        foreach ($cases as $field => $uri) {
            $response = $this->getJson($uri);

            $response->assertStatus(400);
            $this->assertProblemJson($response);
            $this->assertContains($field, array_column($response->json('errors'), 'field'));
        }

        Http::assertNothingSent();
    }

    public function test_overpass_http_error_returns_502_problem_json(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $response = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON);

        $response->assertStatus(502);
        $this->assertProblemJson($response);
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Serviço externo indisponível',
            'status' => 502,
        ]);
        $this->assertIsString($response->json('detail'));
    }

    public function test_overpass_timeout_returns_502_problem_json(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $response = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON);

        $response->assertStatus(502);
        $this->assertProblemJson($response);
        $response->assertJson([
            'type' => 'about:blank',
            'title' => 'Serviço externo indisponível',
            'status' => 502,
        ]);
    }

    public function test_overpass_invalid_body_returns_502(): void
    {
        Http::fake(['*' => Http::response('<html>erro</html>', 200)]);

        $response = $this->getJson('/workshops?lat='.self::LAT.'&lon='.self::LON);

        $response->assertStatus(502);
        $this->assertProblemJson($response);
    }
}
