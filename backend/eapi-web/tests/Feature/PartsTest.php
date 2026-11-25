<?php

namespace Tests\Feature;

use App\Services\JwtService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Peças na EAPI (docs/api/eapi-web.yaml): GET /vehicles/{vehicleId}/parts. A sapi-vehicles é simulada. */
class PartsTest extends TestCase
{
    private const URL = 'http://sapi-vehicles.test';

    private const USER_ID = '0b6f3a3e-6a3c-4d57-9c1b-2f1f0d6f7a11';

    private const VEHICLE_ID = '5d1d7b0e-3f4a-4a52-9a07-0a4c9b7d1c22';

    private const PART = [
        'id' => '5a0d9c3e-7b21-4f68-8e4a-1c9b6d2f0a77',
        'vehicleId' => self::VEHICLE_ID,
        'name' => 'Óleo e filtro de óleo',
        'intervalKm' => 10000,
        'intervalMonths' => 12,
        'intervalSource' => 'UTILIZADOR',
        'lastServiceKm' => 55000,
        'lastServiceDate' => '2026-06-10',
        'nextKm' => 65000,
        'nextDate' => '2027-06-10',
        'kmRemaining' => 5000,
        'daysRemaining' => 197,
    ];

    /** A part with a km interval only: the date values are null. */
    private const PART_WITHOUT_MONTHS = [
        'id' => '7c2e1a4b-9d3f-4b6a-8c5e-2f1a0b9c8d7e',
        'intervalMonths' => null,
        'intervalSource' => 'CATALOGO',
        'nextDate' => null,
        'daysRemaining' => null,
    ] + self::PART;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sapi_vehicles.url' => self::URL,
            'jwt.secret' => 'test-secret-with-more-than-32-characters!',
            'jwt.ttl' => 3600,
        ]);
    }

    private function bearer(): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->issue(self::USER_ID)['token']];
    }

    private function path(): string
    {
        return '/vehicles/'.self::VEHICLE_ID.'/parts';
    }

    public function test_requires_a_valid_token(): void
    {
        Http::fake();

        $this->getJson($this->path())->assertUnauthorized();
        $this->getJson($this->path(), ['Authorization' => 'Bearer not-a-token'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_returns_the_parts_with_only_the_contract_fields(): void
    {
        Http::fake([self::URL.$this->path() => Http::response([self::PART + ['secret' => 'x'], self::PART_WITHOUT_MONTHS], 200)]);

        $this->getJson($this->path(), $this->bearer())
            ->assertOk()
            ->assertExactJson([self::PART, self::PART_WITHOUT_MONTHS]);
    }

    public function test_sends_the_user_id_of_the_token_and_ignores_the_one_from_the_client(): void
    {
        Http::fake([self::URL.$this->path() => Http::response([self::PART], 200)]);

        $this->getJson($this->path(), $this->bearer() + ['X-User-Id' => '11111111-1111-4111-8111-111111111111'])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->url() === self::URL.$this->path()
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_returns_an_empty_list_when_there_are_no_parts(): void
    {
        Http::fake([self::URL.$this->path() => Http::response([], 200)]);

        $this->getJson($this->path(), $this->bearer())->assertOk()->assertExactJson([]);
    }

    public function test_an_id_that_is_not_a_uuid_is_404_without_calling_the_sapi(): void
    {
        Http::fake([self::URL.$this->path() => Http::response([self::PART], 200)]);

        // A valid id works: so the 404 below does not come from the route being missing.
        $this->getJson($this->path(), $this->bearer())->assertOk();

        $this->getJson('/vehicles/not-a-uuid/parts', $this->bearer())->assertNotFound();

        Http::assertSentCount(1);
    }

    public function test_passes_the_404_of_a_vehicle_that_is_not_the_users(): void
    {
        Http::fake([self::URL.$this->path() => Http::response([
            'type' => 'about:blank', 'title' => 'Não encontrado', 'status' => 404, 'detail' => 'Resposta da sapi-vehicles.',
        ], 404)]);

        $this->getJson($this->path(), $this->bearer())
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'Resposta da sapi-vehicles.');
    }

    public function test_a_sapi_error_returns_502_and_hides_the_details(): void
    {
        Http::fake([self::URL.$this->path() => Http::response('SQLSTATE secret', 500)]);

        $response = $this->getJson($this->path(), $this->bearer())
            ->assertStatus(502)
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_sapi_unreachable_returns_502(): void
    {
        Http::fake([self::URL.$this->path() => Http::failedConnection()]);

        $this->getJson($this->path(), $this->bearer())->assertStatus(502);
    }
}
