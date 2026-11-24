<?php

namespace Tests\Feature;

use App\Services\JwtService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Manutenções e despesas na EAPI (docs/api/eapi-web.yaml): valida, põe o X-User-Id do JWT e reencaminha
 * para a sapi-vehicles.
 */
class MaintenancesTest extends TestCase
{
    private const URL = 'http://sapi-vehicles.test';

    private const USER_ID = '0b6f3a3e-6a3c-4d57-9c1b-2f1f0d6f7a11';

    private const VEHICLE_ID = '5d1d7b0e-3f4a-4a52-9a07-0a4c9b7d1c22';

    private const MAINTENANCE_ID = 'c3a7a3f0-2f5e-4b6a-9d11-6a1f3c0b8e44';

    private const PART_ID = '7c0e5b8a-1f26-4d0b-8c7e-3b9a4f5d6e70';

    private const MAINTENANCE = [
        'id' => self::MAINTENANCE_ID,
        'vehicleId' => self::VEHICLE_ID,
        'type' => 'MUDANCA_OLEO',
        'date' => '2026-10-18',
        'km' => 67440,
        'cost' => 146.0,
        'notes' => 'Oficina Bosch',
        'parts' => [['partId' => self::PART_ID, 'partName' => 'Pneus', 'cost' => 60.0]],
        'createdAt' => '2026-11-06T10:48:00Z',
    ];

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

    private function sapiProblem(int $status, string $title, string $detail, array $errors = []): array
    {
        return array_filter(['type' => 'about:blank', 'title' => $title, 'status' => $status, 'detail' => $detail, 'errors' => $errors ?: null]);
    }

    private function path(string $suffix = ''): string
    {
        return '/vehicles/'.self::VEHICLE_ID.'/maintenances'.$suffix;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'MUDANCA_OLEO',
            'date' => '2026-11-20',
            'km' => 68000,
            'cost' => 146.5,
            'notes' => 'Oficina Bosch',
            'parts' => [['partId' => self::PART_ID, 'cost' => 60]],
        ], $overrides);
    }

    // ---------- authentication ----------

    public function test_every_route_requires_a_token(): void
    {
        Http::fake();

        $this->getJson($this->path())->assertUnauthorized();
        $this->postJson($this->path(), $this->payload())->assertUnauthorized();
        $this->getJson($this->path('/'.self::MAINTENANCE_ID))->assertUnauthorized();
        $this->patchJson($this->path('/'.self::MAINTENANCE_ID), ['cost' => 1])->assertUnauthorized();
        $this->getJson('/vehicles/'.self::VEHICLE_ID.'/expenses')->assertUnauthorized();

        Http::assertNothingSent();
    }

    // ---------- GET list / show ----------

    public function test_list_returns_the_history_with_the_user_of_the_token(): void
    {
        Http::fake([self::URL.$this->path() => Http::response([self::MAINTENANCE + ['secret' => 'x']], 200)]);

        $this->getJson($this->path(), $this->bearer() + ['X-User-Id' => '11111111-1111-4111-8111-111111111111'])
            ->assertOk()
            ->assertExactJson([self::MAINTENANCE]);

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_show_returns_one_maintenance(): void
    {
        Http::fake([self::URL.$this->path('/'.self::MAINTENANCE_ID) => Http::response(self::MAINTENANCE, 200)]);

        $this->getJson($this->path('/'.self::MAINTENANCE_ID), $this->bearer())
            ->assertOk()
            ->assertExactJson(self::MAINTENANCE);
    }

    public function test_a_404_from_the_sapi_is_passed_on(): void
    {
        Http::fake([self::URL.'/*' => Http::response($this->sapiProblem(404, 'Não encontrado', 'O recurso pedido não existe.'), 404)]);

        $this->getJson($this->path(), $this->bearer())->assertNotFound()->assertHeader('Content-Type', 'application/problem+json');
        $this->getJson($this->path('/'.self::MAINTENANCE_ID), $this->bearer())->assertNotFound();
        $this->getJson('/vehicles/'.self::VEHICLE_ID.'/expenses', $this->bearer())->assertNotFound();
    }

    public function test_ids_that_are_not_uuids_are_404_without_calling_the_sapi(): void
    {
        Http::fake();

        $this->getJson('/vehicles/not-a-uuid/maintenances', $this->bearer())->assertNotFound();
        $this->getJson($this->path('/not-a-uuid'), $this->bearer())->assertNotFound();
        $this->patchJson($this->path('/not-a-uuid'), ['cost' => 1], $this->bearer())->assertNotFound();
        $this->postJson('/vehicles/not-a-uuid/maintenances', $this->payload(), $this->bearer())->assertNotFound();
        $this->getJson('/vehicles/not-a-uuid/expenses', $this->bearer())->assertNotFound();

        Http::assertNothingSent();
    }

    // ---------- POST ----------

    public function test_create_forwards_the_maintenance_and_returns_201_with_the_closed_alerts(): void
    {
        $created = self::MAINTENANCE + ['closedAlertIds' => ['a1d2c3b4-0000-4000-8000-000000000001']];
        Http::fake([self::URL.$this->path() => Http::response($created, 201)]);

        $this->postJson($this->path(), $this->payload(), $this->bearer())
            ->assertCreated()
            ->assertExactJson($created);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->data() == $this->payload()
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_create_forwards_only_the_contract_fields(): void
    {
        Http::fake([self::URL.$this->path() => Http::response(self::MAINTENANCE + ['closedAlertIds' => []], 201)]);

        $this->postJson($this->path(), $this->payload([
            'userId' => '11111111-1111-4111-8111-111111111111',
            'vehicleId' => '22222222-2222-4222-8222-222222222222',
            'parts' => [['partId' => self::PART_ID, 'cost' => 60, 'extra' => 'x']],
        ]), $this->bearer())->assertCreated();

        Http::assertSent(fn (Request $r) => $r->data() == $this->payload());
    }

    public function test_create_without_km_and_parts_lets_the_sapi_use_its_defaults(): void
    {
        Http::fake([self::URL.$this->path() => Http::response(self::MAINTENANCE + ['closedAlertIds' => []], 201)]);

        $this->postJson($this->path(), ['type' => 'REPARACAO', 'date' => '2026-11-20', 'cost' => 50, 'km' => null], $this->bearer())
            ->assertCreated();

        // No km (null), no notes and no parts are sent: the SAPI uses the current km of the vehicle.
        Http::assertSent(fn (Request $r) => $r->data() == ['type' => 'REPARACAO', 'date' => '2026-11-20', 'cost' => 50]);
    }

    public function test_create_reports_every_invalid_field_without_calling_the_sapi(): void
    {
        Http::fake();

        $response = $this->postJson($this->path(), [
            'type' => 'LAVAGEM',
            'date' => '20/11/2026',
            'km' => -5,
            'cost' => 10.999,
            'notes' => str_repeat('a', 1001),
            'parts' => [['partId' => 'abc', 'cost' => -1]],
        ], $this->bearer())
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json');

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['cost', 'date', 'km', 'notes', 'parts.0.cost', 'parts.0.partId', 'type'], $fields);
        Http::assertNothingSent();
    }

    public function test_create_requires_type_date_and_cost(): void
    {
        Http::fake();

        $response = $this->postJson($this->path(), [], $this->bearer())->assertStatus(400);

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['cost', 'date', 'type'], $fields);
        Http::assertNothingSent();
    }

    public function test_create_rejects_a_future_date_and_a_repeated_part(): void
    {
        Http::fake();

        $this->postJson($this->path(), $this->payload(['date' => now()->addDay()->toDateString()]), $this->bearer())
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'date');
        $this->postJson($this->path(), $this->payload(['parts' => [['partId' => self::PART_ID], ['partId' => self::PART_ID]]]), $this->bearer())
            ->assertStatus(400);

        Http::assertNothingSent();
    }

    public function test_create_sends_the_part_id_in_lower_case_and_catches_a_part_repeated_in_another_case(): void
    {
        Http::fake([self::URL.$this->path() => Http::response(self::MAINTENANCE + ['closedAlertIds' => []], 201)]);

        $this->postJson($this->path(), $this->payload(['parts' => [['partId' => strtoupper(self::PART_ID), 'cost' => 60]]]), $this->bearer())
            ->assertCreated();
        Http::assertSent(fn (Request $r) => $r->data()['parts'][0]['partId'] === self::PART_ID);

        $response = $this->postJson($this->path(), $this->payload(['parts' => [['partId' => self::PART_ID], ['partId' => strtoupper(self::PART_ID)]]]), $this->bearer())
            ->assertStatus(400);

        // The "distinct" rule flags every position that is repeated.
        $this->assertContains('parts.1.partId', array_column($response->json('errors'), 'field'));
    }

    public function test_create_passes_the_part_error_of_the_sapi(): void
    {
        $errors = [['field' => 'parts.0.partId', 'message' => 'A peça não existe neste veículo.']];
        Http::fake([self::URL.$this->path() => Http::response($this->sapiProblem(400, 'Pedido inválido', 'Há campos inválidos.', $errors), 400)]);

        $this->postJson($this->path(), $this->payload(), $this->bearer())
            ->assertStatus(400)
            ->assertJsonPath('errors', $errors);
    }

    // ---------- PATCH ----------

    public function test_patch_forwards_only_cost_and_notes(): void
    {
        Http::fake([self::URL.$this->path('/'.self::MAINTENANCE_ID) => Http::response(['cost' => 160.75] + self::MAINTENANCE, 200)]);

        $this->patchJson($this->path('/'.self::MAINTENANCE_ID), [
            'cost' => 160.75, 'notes' => null, 'type' => 'AVARIA', 'date' => '2020-01-01', 'km' => 1, 'parts' => [],
        ], $this->bearer())
            ->assertOk()
            ->assertJsonPath('cost', 160.75);

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->data() === ['cost' => 160.75, 'notes' => null]
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_patch_requires_cost_or_notes_and_validates_them_without_calling_the_sapi(): void
    {
        Http::fake();
        $url = $this->path('/'.self::MAINTENANCE_ID);

        $this->patchJson($url, [], $this->bearer())->assertStatus(400)->assertHeader('Content-Type', 'application/problem+json');
        $this->patchJson($url, ['km' => 1], $this->bearer())->assertStatus(400);
        $this->patchJson($url, ['cost' => -1], $this->bearer())->assertStatus(400)->assertJsonPath('errors.0.field', 'cost');
        $this->patchJson($url, ['cost' => null], $this->bearer())->assertStatus(400)->assertJsonPath('errors.0.field', 'cost');
        $this->patchJson($url, ['notes' => str_repeat('a', 1001)], $this->bearer())->assertStatus(400)->assertJsonPath('errors.0.field', 'notes');

        Http::assertNothingSent();
    }

    // ---------- GET expenses ----------

    public function test_expenses_returns_the_list_of_the_sapi(): void
    {
        $expenses = [
            ['id' => 'e1', 'category' => 'MANUTENCAO', 'date' => '2026-10-18', 'amount' => 146.0, 'description' => 'Oficina Bosch', 'km' => 67440],
            ['id' => 'e2', 'category' => 'IUC', 'date' => '2026-01-20', 'amount' => 51.5, 'description' => null, 'km' => null],
        ];
        Http::fake([self::URL.'/vehicles/'.self::VEHICLE_ID.'/expenses' => Http::response([$expenses[0] + ['secret' => 'x'], $expenses[1]], 200)]);

        $this->getJson('/vehicles/'.self::VEHICLE_ID.'/expenses', $this->bearer())
            ->assertOk()
            ->assertExactJson($expenses);

        Http::assertSent(fn (Request $r) => $r->header('X-User-Id') === [self::USER_ID]);
    }

    // ---------- SAPI unavailable ----------

    public function test_sapi_failures_return_502_and_hide_the_details(): void
    {
        Http::fake([self::URL.'/*' => Http::response('SQLSTATE secret', 500)]);

        $this->getJson($this->path(), $this->bearer())
            ->assertStatus(502)
            ->assertJsonMissing(['detail' => 'SQLSTATE secret']);
        $this->postJson($this->path(), $this->payload(), $this->bearer())->assertStatus(502);
        $this->getJson('/vehicles/'.self::VEHICLE_ID.'/expenses', $this->bearer())->assertStatus(502);
    }
}
