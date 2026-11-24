<?php

namespace Tests\Feature;

use App\Services\JwtService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Prazos na EAPI (docs/api/eapi-web.yaml): GET e POST /vehicles/{vehicleId}/deadlines. */
class DeadlinesTest extends TestCase
{
    private const URL = 'http://sapi-vehicles.test';

    private const USER_ID = '0b6f3a3e-6a3c-4d57-9c1b-2f1f0d6f7a11';

    private const VEHICLE_ID = '5d1d7b0e-3f4a-4a52-9a07-0a4c9b7d1c22';

    private const DEADLINE = [
        'id' => '9a1f5c2e-7d3b-4e8a-b6c4-1d2e3f4a5b6c',
        'vehicleId' => self::VEHICLE_ID,
        'type' => 'IUC',
        'status' => 'PENDENTE',
        'dueDate' => '2027-01-31',
        'paidAt' => null,
        'amount' => null,
        'description' => null,
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

    private function sapiProblem(int $status, string $title, string $detail): array
    {
        return ['type' => 'about:blank', 'title' => $title, 'status' => $status, 'detail' => $detail];
    }

    private function path(): string
    {
        return '/vehicles/'.self::VEHICLE_ID.'/deadlines';
    }

    public function test_both_routes_require_a_token(): void
    {
        Http::fake();

        $this->getJson($this->path())->assertUnauthorized();
        $this->postJson($this->path(), ['type' => 'IUC', 'dueDate' => '2027-01-31'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_list_returns_the_deadlines_of_the_vehicle(): void
    {
        $paid = ['status' => 'CONCLUIDO', 'paidAt' => '2026-01-10', 'amount' => 146.0, 'description' => 'IUC 2025'] + self::DEADLINE;
        Http::fake([self::URL.$this->path() => Http::response([self::DEADLINE, $paid + ['secret' => 'x']], 200)]);

        $this->getJson($this->path(), $this->bearer())
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.status', 'PENDENTE')
            ->assertJsonPath('1.status', 'CONCLUIDO')
            ->assertJsonPath('1.amount', 146)
            ->assertJsonMissingPath('1.secret');

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_list_of_a_vehicle_that_is_not_the_users_is_404(): void
    {
        Http::fake([self::URL.$this->path() => Http::response($this->sapiProblem(404, 'Não encontrado', 'O recurso pedido não existe.'), 404)]);

        $this->getJson($this->path(), $this->bearer())
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_create_forwards_the_deadline_and_returns_201(): void
    {
        Http::fake([self::URL.$this->path() => Http::response(self::DEADLINE, 201)]);

        $this->postJson($this->path(), ['type' => 'IUC', 'dueDate' => '2027-01-31'], $this->bearer())
            ->assertCreated()
            ->assertExactJson(self::DEADLINE);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->data() === ['type' => 'IUC', 'dueDate' => '2027-01-31']
            && $r->header('X-User-Id') === [self::USER_ID]);
    }

    public function test_create_forwards_only_type_and_due_date(): void
    {
        Http::fake([self::URL.$this->path() => Http::response(self::DEADLINE, 201)]);

        $this->postJson($this->path(), [
            'type' => 'IUC', 'dueDate' => '2027-01-31', 'status' => 'CONCLUIDO', 'amount' => 1, 'userId' => 'x',
        ], $this->bearer())->assertCreated();

        Http::assertSent(fn (Request $r) => $r->data() === ['type' => 'IUC', 'dueDate' => '2027-01-31']);
    }

    public function test_create_reports_invalid_fields_without_calling_the_sapi(): void
    {
        Http::fake();

        foreach ([
            [['dueDate' => '2027-01-31'], 'type'],
            [['type' => 'IPO', 'dueDate' => '2027-01-31'], 'type'],
            [['type' => 'IUC'], 'dueDate'],
            [['type' => 'IUC', 'dueDate' => '31/01/2027'], 'dueDate'],
            [['type' => 'IUC', 'dueDate' => '2027-02-30'], 'dueDate'],
        ] as [$body, $field]) {
            $this->postJson($this->path(), $body, $this->bearer())
                ->assertStatus(400)
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('errors.0.field', $field);
        }

        Http::assertNothingSent();
    }

    public function test_create_passes_the_409_of_a_second_pending_deadline(): void
    {
        Http::fake([self::URL.$this->path() => Http::response($this->sapiProblem(409, 'Conflito', 'Já existe um prazo pendente deste tipo para este veículo.'), 409)]);

        $this->postJson($this->path(), ['type' => 'IUC', 'dueDate' => '2027-01-31'], $this->bearer())
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'Já existe um prazo pendente deste tipo para este veículo.');
    }

    public function test_an_id_that_is_not_a_uuid_is_404_without_calling_the_sapi(): void
    {
        Http::fake();

        $this->getJson('/vehicles/not-a-uuid/deadlines', $this->bearer())->assertNotFound();
        $this->postJson('/vehicles/not-a-uuid/deadlines', ['type' => 'IUC', 'dueDate' => '2027-01-31'], $this->bearer())->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_sapi_down_returns_502(): void
    {
        Http::fake([self::URL.$this->path() => Http::response('SQLSTATE secret', 500)]);

        $this->getJson($this->path(), $this->bearer())
            ->assertStatus(502)
            ->assertJsonMissing(['detail' => 'SQLSTATE secret']);
    }
}
