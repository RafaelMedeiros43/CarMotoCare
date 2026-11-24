<?php

namespace Tests\Feature;

use App\Services\JwtService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** CRUD de veículos da EAPI (docs/api/eapi-web.yaml): valida, põe o X-User-Id do JWT e reencaminha para a sapi-vehicles. */
class VehiclesTest extends TestCase
{
    private const URL = 'http://sapi-vehicles.test';

    private const USER_ID = '0b6f3a3e-6a3c-4d57-9c1b-2f1f0d6f7a11';

    private const VEHICLE_ID = '5d1d7b0e-3f4a-4a52-9a07-0a4c9b7d1c22';

    private const VEHICLE = [
        'id' => self::VEHICLE_ID,
        'userId' => self::USER_ID,
        'type' => 'CARRO',
        'brand' => 'Volkswagen',
        'model' => 'Golf',
        'firstRegistration' => '2021-06',
        'plate' => 'AB-24-CD',
        'km' => 68420,
        'nextService' => [
            'partId' => '7c0e5b8a-1f26-4d0b-8c7e-3b9a4f5d6e70',
            'partName' => 'Pneus',
            'targetKm' => 70000,
            'kmRemaining' => 1580,
            'dueDate' => null,
        ],
        'openAlerts' => 1,
        'createdAt' => '2026-11-06T10:48:00Z',
    ];

    private const CREATE = [
        'type' => 'CARRO',
        'brand' => 'Volkswagen',
        'model' => 'Golf',
        'firstRegistration' => '2021-06',
        'plate' => 'AB-24-CD',
        'km' => 68420,
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

    // ---------- authentication ----------

    public function test_every_vehicle_route_requires_a_token(): void
    {
        Http::fake();
        $id = self::VEHICLE_ID;

        $this->getJson('/vehicles')->assertUnauthorized();
        $this->postJson('/vehicles', self::CREATE)->assertUnauthorized();
        $this->getJson("/vehicles/{$id}")->assertUnauthorized();
        $this->patchJson("/vehicles/{$id}", ['model' => 'Polo'])->assertUnauthorized();
        $this->deleteJson("/vehicles/{$id}")->assertUnauthorized();

        Http::assertNothingSent();
    }

    // ---------- GET /vehicles ----------

    public function test_list_returns_the_vehicles_and_the_total_count_header(): void
    {
        Http::fake([self::URL.'/vehicles*' => Http::response([self::VEHICLE], 200, ['X-Total-Count' => '3'])]);

        $this->getJson('/vehicles?type=CARRO&limit=1&offset=2', $this->bearer())
            ->assertOk()
            ->assertHeader('X-Total-Count', '3')
            ->assertJsonCount(1)
            ->assertExactJson([self::VEHICLE]);

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r['type'] === 'CARRO' && $r['limit'] === '1' && $r['offset'] === '2'
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_list_uses_the_user_of_the_token_and_ignores_a_header_sent_by_the_client(): void
    {
        Http::fake([self::URL.'/vehicles*' => Http::response([], 200, ['X-Total-Count' => '0'])]);

        $this->getJson('/vehicles', $this->bearer() + ['X-User-Id' => '11111111-1111-4111-8111-111111111111'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_list_does_not_forward_unknown_query_parameters(): void
    {
        Http::fake([self::URL.'/vehicles*' => Http::response([], 200, ['X-Total-Count' => '0'])]);

        $this->getJson('/vehicles?userId=x&foo=bar', $this->bearer())->assertOk();

        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'userId') && ! str_contains($r->url(), 'foo'));
    }

    public function test_list_rejects_invalid_query_parameters_without_calling_the_sapi(): void
    {
        Http::fake();

        foreach (['type=BICICLETA', 'limit=0', 'limit=101', 'limit=abc', 'offset=-1'] as $query) {
            $this->getJson("/vehicles?{$query}", $this->bearer())
                ->assertStatus(400)
                ->assertHeader('Content-Type', 'application/problem+json');
        }

        Http::assertNothingSent();
    }

    public function test_list_returns_only_the_contract_fields(): void
    {
        Http::fake([self::URL.'/vehicles*' => Http::response([self::VEHICLE + ['secret' => 'x']], 200, ['X-Total-Count' => '1'])]);

        $this->getJson('/vehicles', $this->bearer())
            ->assertOk()
            ->assertJsonMissingPath('0.secret');
    }

    // ---------- POST /vehicles ----------

    public function test_create_forwards_the_vehicle_and_returns_201(): void
    {
        Http::fake([self::URL.'/vehicles' => Http::response(self::VEHICLE, 201)]);

        $this->postJson('/vehicles', self::CREATE, $this->bearer())
            ->assertCreated()
            ->assertExactJson(self::VEHICLE);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->data() === self::CREATE
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_create_without_dates_creates_no_deadlines(): void
    {
        Http::fake([self::URL.'/vehicles' => Http::response(self::VEHICLE, 201)]);

        $this->postJson('/vehicles', self::CREATE + ['iucValidUntil' => null, 'insuranceValidUntil' => ''], $this->bearer())->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->data() === self::CREATE);
    }

    public function test_create_creates_one_pending_deadline_per_date_given(): void
    {
        Http::fake([
            self::URL.'/vehicles/'.self::VEHICLE_ID.'/deadlines' => Http::response(['id' => 'x'], 201),
            self::URL.'/vehicles' => Http::response(self::VEHICLE, 201),
        ]);

        $this->postJson('/vehicles', self::CREATE + [
            'iucValidUntil' => '2027-01-31',
            'insuranceValidUntil' => '2027-02-28',
            'inspectionValidUntil' => null,
        ], $this->bearer())
            ->assertCreated()
            ->assertExactJson(self::VEHICLE);

        // The vehicle first (without the dates), then one deadline per date, all with the user of the token.
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/vehicles' && $r->data() === self::CREATE);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/deadlines')
            && $r->data() === ['type' => 'IUC', 'dueDate' => '2027-01-31']
            && $r->header('X-User-Id') === [self::USER_ID]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/deadlines')
            && $r->data() === ['type' => 'SEGURO', 'dueDate' => '2027-02-28']);
    }

    public function test_create_removes_the_vehicle_when_a_deadline_cannot_be_created(): void
    {
        Http::fake([
            self::URL.'/vehicles/'.self::VEHICLE_ID.'/deadlines' => Http::response('boom', 500),
            self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response('', 204),
            self::URL.'/vehicles' => Http::response(self::VEHICLE, 201),
        ]);

        // No half-created vehicle is left behind: it is deleted and the error is returned.
        $this->postJson('/vehicles', self::CREATE + ['iucValidUntil' => '2027-01-31'], $this->bearer())
            ->assertStatus(502)
            ->assertHeader('Content-Type', 'application/problem+json');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE'
            && $r->url() === self::URL.'/vehicles/'.self::VEHICLE_ID
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_create_returns_the_original_error_even_if_removing_the_vehicle_also_fails(): void
    {
        Http::fake([
            self::URL.'/vehicles/'.self::VEHICLE_ID.'/deadlines' => Http::response('boom', 500),
            self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response('boom', 500),
            self::URL.'/vehicles' => Http::response(self::VEHICLE, 201),
        ]);

        $this->postJson('/vehicles', self::CREATE + ['iucValidUntil' => '2027-01-31'], $this->bearer())
            ->assertStatus(502);
    }

    public function test_create_stops_at_the_first_deadline_that_fails(): void
    {
        Http::fake([
            self::URL.'/vehicles/'.self::VEHICLE_ID.'/deadlines' => Http::response('boom', 500),
            self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response('', 204),
            self::URL.'/vehicles' => Http::response(self::VEHICLE, 201),
        ]);

        $this->postJson('/vehicles', self::CREATE + ['iucValidUntil' => '2027-01-31', 'insuranceValidUntil' => '2027-02-28'], $this->bearer())
            ->assertStatus(502);

        // vehicle + first deadline + delete (the second deadline is not tried)
        Http::assertSentCount(3);
    }

    public function test_create_never_forwards_a_user_id_from_the_body(): void
    {
        Http::fake([self::URL.'/vehicles' => Http::response(self::VEHICLE, 201)]);

        $this->postJson('/vehicles', self::CREATE + ['userId' => '11111111-1111-4111-8111-111111111111'], $this->bearer())->assertCreated();

        Http::assertSent(fn (Request $r) => ! array_key_exists('userId', $r->data()) && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_create_reports_every_invalid_field_without_calling_the_sapi(): void
    {
        Http::fake();

        $response = $this->postJson('/vehicles', [
            'type' => 'CAMIAO',
            'brand' => '',
            'model' => str_repeat('a', 51),
            'firstRegistration' => '2021-13',
            'plate' => 'AB24CD',
            'km' => -1,
            'iucValidUntil' => '31/01/2027',
        ], $this->bearer())
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json');

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['brand', 'firstRegistration', 'iucValidUntil', 'km', 'model', 'plate', 'type'], $fields);
        Http::assertNothingSent();
    }

    public function test_create_requires_the_mandatory_fields(): void
    {
        Http::fake();

        $response = $this->postJson('/vehicles', [], $this->bearer())->assertStatus(400);

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['brand', 'firstRegistration', 'km', 'model', 'plate', 'type'], $fields);
        Http::assertNothingSent();
    }

    public function test_create_passes_the_duplicate_plate_error_from_the_sapi(): void
    {
        $errors = [['field' => 'plate', 'message' => 'Já existe um veículo com esta matrícula.']];
        Http::fake([self::URL.'/vehicles' => Http::response($this->sapiProblem(400, 'Pedido inválido', 'Há campos inválidos.', $errors), 400)]);

        $this->postJson('/vehicles', self::CREATE, $this->bearer())
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('errors', $errors);
    }

    // ---------- GET /vehicles/{vehicleId} ----------

    public function test_show_returns_the_vehicle(): void
    {
        Http::fake([self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response(self::VEHICLE, 200)]);

        $this->getJson('/vehicles/'.self::VEHICLE_ID, $this->bearer())
            ->assertOk()
            ->assertExactJson(self::VEHICLE);

        Http::assertSent(fn (Request $r) => $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_show_of_a_vehicle_that_is_not_the_users_is_404(): void
    {
        Http::fake([self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response($this->sapiProblem(404, 'Não encontrado', 'O recurso pedido não existe.'), 404)]);

        $this->getJson('/vehicles/'.self::VEHICLE_ID, $this->bearer())
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_an_id_that_is_not_a_uuid_is_404_without_calling_the_sapi(): void
    {
        Http::fake();

        $this->getJson('/vehicles/not-a-uuid', $this->bearer())->assertNotFound();
        $this->patchJson('/vehicles/not-a-uuid', ['model' => 'Polo'], $this->bearer())->assertNotFound();
        $this->deleteJson('/vehicles/not-a-uuid', [], $this->bearer())->assertNotFound();

        Http::assertNothingSent();
    }

    // ---------- PATCH /vehicles/{vehicleId} ----------

    public function test_patch_forwards_only_the_descriptive_fields(): void
    {
        Http::fake([self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response(['model' => 'Polo'] + self::VEHICLE, 200)]);

        $this->patchJson('/vehicles/'.self::VEHICLE_ID, [
            'model' => 'Polo',
            'plate' => 'ZZ-99-ZZ',
            'km' => 1,
            'type' => 'MOTO',
            'userId' => '11111111-1111-4111-8111-111111111111',
        ], $this->bearer())
            ->assertOk()
            ->assertJsonPath('model', 'Polo');

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->data() === ['model' => 'Polo', 'plate' => 'ZZ-99-ZZ']
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_patch_requires_at_least_one_descriptive_field(): void
    {
        Http::fake();

        $this->patchJson('/vehicles/'.self::VEHICLE_ID, [], $this->bearer())->assertStatus(400);
        $this->patchJson('/vehicles/'.self::VEHICLE_ID, ['km' => 1], $this->bearer())->assertStatus(400);

        Http::assertNothingSent();
    }

    public function test_patch_rejects_invalid_fields_without_calling_the_sapi(): void
    {
        Http::fake();

        $response = $this->patchJson('/vehicles/'.self::VEHICLE_ID, [
            'brand' => '',
            'firstRegistration' => '2021-6',
            'plate' => 'AB24CD',
        ], $this->bearer())->assertStatus(400);

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['brand', 'firstRegistration', 'plate'], $fields);
        Http::assertNothingSent();
    }

    public function test_patch_of_an_unknown_vehicle_is_404(): void
    {
        Http::fake([self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response($this->sapiProblem(404, 'Não encontrado', 'O recurso pedido não existe.'), 404)]);

        $this->patchJson('/vehicles/'.self::VEHICLE_ID, ['model' => 'Polo'], $this->bearer())->assertNotFound();
    }

    // ---------- DELETE /vehicles/{vehicleId} ----------

    public function test_delete_returns_204_without_a_body(): void
    {
        Http::fake([self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response('', 204)]);

        $this->deleteJson('/vehicles/'.self::VEHICLE_ID, [], $this->bearer())
            ->assertNoContent();

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_delete_of_an_unknown_vehicle_is_404(): void
    {
        Http::fake([self::URL.'/vehicles/'.self::VEHICLE_ID => Http::response($this->sapiProblem(404, 'Não encontrado', 'O recurso pedido não existe.'), 404)]);

        $this->deleteJson('/vehicles/'.self::VEHICLE_ID, [], $this->bearer())->assertNotFound();
    }

    // ---------- SAPI unavailable ----------

    public function test_sapi_down_returns_502_problem(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->getJson('/vehicles', $this->bearer())
            ->assertStatus(502)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_sapi_server_error_returns_502_and_hides_the_details(): void
    {
        Http::fake([self::URL.'/vehicles' => Http::response('SQLSTATE secret', 500)]);

        $this->postJson('/vehicles', self::CREATE, $this->bearer())
            ->assertStatus(502)
            ->assertJsonMissing(['detail' => 'SQLSTATE secret']);
    }
}
