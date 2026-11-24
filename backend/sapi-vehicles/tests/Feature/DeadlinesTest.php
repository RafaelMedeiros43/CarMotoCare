<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Prazos legais (docs/api/sapi-vehicles.yaml): GET/POST /vehicles/{vehicleId}/deadlines e GET /deadlines.
 * O pagamento (PATCH) ainda não está implementado.
 */
class DeadlinesTest extends TestCase
{
    use RefreshDatabase;

    private function user(): string
    {
        $id = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Demo',
            'email' => "{$id}@example.com",
            'password_hash' => 'x',
        ]);

        return $id;
    }

    private function headers(string $userId): array
    {
        return ['X-User-Id' => $userId];
    }

    private function vehicle(?string $userId = null, string $plate = 'AB-24-CD'): Vehicle
    {
        return Vehicle::create([
            'user_id' => $userId ?? $this->user(),
            'type' => 'CARRO',
            'brand' => 'Volkswagen',
            'model' => 'Golf',
            'first_registration' => '2021-06',
            'plate' => $plate,
            'km' => 68420,
        ]);
    }

    private function deadline(Vehicle $vehicle, string $type, string $dueDate, bool $paid = false): Deadline
    {
        return Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => $type,
            'status' => $paid ? 'CONCLUIDO' : 'PENDENTE',
            'due_date' => $dueDate,
            'paid_at' => $paid ? '2026-01-10' : null,
            'amount' => $paid ? 146 : null,
            'description' => $paid ? 'IUC 2025' : null,
        ]);
    }

    // ---------- POST /vehicles/{vehicleId}/deadlines ----------

    public function test_creates_a_pending_deadline_and_returns_201(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);

        $response = $this->postJson("/vehicles/{$vehicle->id}/deadlines", ['type' => 'IUC', 'dueDate' => '2027-01-31'], $this->headers($userId))
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('vehicleId', $vehicle->id)
            ->assertJsonPath('type', 'IUC')
            ->assertJsonPath('status', 'PENDENTE')
            ->assertJsonPath('dueDate', '2027-01-31')
            ->assertJsonPath('paidAt', null)
            ->assertJsonPath('amount', null)
            ->assertJsonPath('description', null);

        $this->assertSame(
            ['id', 'vehicleId', 'type', 'status', 'dueDate', 'paidAt', 'amount', 'description', 'createdAt'],
            array_keys($response->json()),
        );
        $this->assertTrue(Str::isUuid($response->json('id')));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('createdAt'));
        $this->assertDatabaseHas('deadlines', ['id' => $response->json('id'), 'status' => 'PENDENTE', 'due_date' => '2027-01-31']);
    }

    public function test_a_deadline_in_the_past_is_accepted(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/deadlines", ['type' => 'SEGURO', 'dueDate' => '2020-01-01'])
            ->assertCreated()
            ->assertJsonPath('dueDate', '2020-01-01');
    }

    public function test_each_type_can_have_its_own_pending_deadline(): void
    {
        $vehicle = $this->vehicle();

        foreach (['IUC', 'SEGURO', 'INSPECAO'] as $type) {
            $this->postJson("/vehicles/{$vehicle->id}/deadlines", ['type' => $type, 'dueDate' => '2027-01-31'])->assertCreated();
        }

        $this->assertSame(3, $vehicle->deadlines()->count());
    }

    public function test_a_second_pending_deadline_of_the_same_type_is_a_409(): void
    {
        $vehicle = $this->vehicle();
        $this->deadline($vehicle, 'IUC', '2027-01-31');

        $this->postJson("/vehicles/{$vehicle->id}/deadlines", ['type' => 'IUC', 'dueDate' => '2028-01-31'])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409);

        $this->assertSame(1, $vehicle->deadlines()->count());
    }

    public function test_the_same_type_is_allowed_when_the_previous_one_is_already_paid(): void
    {
        $vehicle = $this->vehicle();
        $this->deadline($vehicle, 'IUC', '2026-01-31', paid: true);

        $this->postJson("/vehicles/{$vehicle->id}/deadlines", ['type' => 'IUC', 'dueDate' => '2027-01-31'])->assertCreated();
    }

    public function test_the_same_type_is_allowed_on_another_vehicle(): void
    {
        $this->deadline($this->vehicle(plate: 'AA-11-AA'), 'IUC', '2027-01-31');
        $other = $this->vehicle(plate: 'BB-22-BB');

        $this->postJson("/vehicles/{$other->id}/deadlines", ['type' => 'IUC', 'dueDate' => '2027-01-31'])->assertCreated();
    }

    public function test_create_reports_invalid_fields_by_name(): void
    {
        $vehicle = $this->vehicle();

        foreach ([
            [['type' => 'IPO', 'dueDate' => '2027-01-31'], 'type'],
            [['dueDate' => '2027-01-31'], 'type'],
            [['type' => 'IUC'], 'dueDate'],
            [['type' => 'IUC', 'dueDate' => '31/01/2027'], 'dueDate'],
            [['type' => 'IUC', 'dueDate' => '2027-02-30'], 'dueDate'],
            [['type' => 'IUC', 'dueDate' => 20270131], 'dueDate'],
        ] as [$body, $field]) {
            $this->postJson("/vehicles/{$vehicle->id}/deadlines", $body)
                ->assertStatus(400)
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('errors.0.field', $field);
        }

        $this->assertSame(0, $vehicle->deadlines()->count());
    }

    public function test_create_ignores_status_and_payment_fields(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/deadlines", [
            'type' => 'IUC', 'dueDate' => '2027-01-31', 'status' => 'CONCLUIDO', 'amount' => 99, 'paidAt' => '2026-01-01',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'PENDENTE')
            ->assertJsonPath('amount', null)
            ->assertJsonPath('paidAt', null);
    }

    public function test_create_on_another_users_or_unknown_vehicle_is_404(): void
    {
        $vehicle = $this->vehicle();
        $body = ['type' => 'IUC', 'dueDate' => '2027-01-31'];

        $this->postJson("/vehicles/{$vehicle->id}/deadlines", $body, $this->headers($this->user()))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
        $this->postJson('/vehicles/'.Str::uuid().'/deadlines', $body)->assertNotFound();
        $this->postJson('/vehicles/not-a-uuid/deadlines', $body)->assertNotFound();

        $this->assertSame(0, $vehicle->deadlines()->count());
    }

    public function test_create_rejects_a_header_that_is_not_a_uuid(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/deadlines", ['type' => 'IUC', 'dueDate' => '2027-01-31'], ['X-User-Id' => 'abc'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'X-User-Id');
    }

    // ---------- GET /vehicles/{vehicleId}/deadlines ----------

    public function test_list_returns_the_deadlines_of_the_vehicle_pending_first(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $paidOld = $this->deadline($vehicle, 'IUC', '2025-01-31', paid: true);
        $paidRecent = $this->deadline($vehicle, 'SEGURO', '2026-03-31', paid: true);
        $pendingLate = $this->deadline($vehicle, 'INSPECAO', '2027-06-30');
        $pendingSoon = $this->deadline($vehicle, 'IUC', '2027-01-31');
        $this->deadline($this->vehicle(plate: 'ZZ-99-ZZ'), 'IUC', '2027-01-31'); // outro veículo

        $response = $this->getJson("/vehicles/{$vehicle->id}/deadlines", $this->headers($userId))
            ->assertOk()
            ->assertJsonCount(4);

        // Pendentes primeiro (data mais próxima primeiro); depois os concluídos, o mais recente primeiro.
        $this->assertSame(
            [$pendingSoon->id, $pendingLate->id, $paidRecent->id, $paidOld->id],
            array_column($response->json(), 'id'),
        );
    }

    public function test_list_shows_the_payment_data_of_a_paid_deadline_with_amount_as_a_number(): void
    {
        $vehicle = $this->vehicle();
        $this->deadline($vehicle, 'IUC', '2026-01-31', paid: true);

        $response = $this->getJson("/vehicles/{$vehicle->id}/deadlines")
            ->assertOk()
            ->assertJsonPath('0.status', 'CONCLUIDO')
            ->assertJsonPath('0.paidAt', '2026-01-10')
            ->assertJsonPath('0.description', 'IUC 2025');

        // A JSON number (146 or 146.0), not the text "146.00".
        $this->assertIsNotString($response->json('0.amount'));
        $this->assertEquals(146, $response->json('0.amount'));
    }

    public function test_list_is_empty_for_a_vehicle_without_deadlines(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/deadlines")->assertOk()->assertExactJson([]);
    }

    public function test_list_of_another_users_or_unknown_vehicle_is_404(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/deadlines", $this->headers($this->user()))->assertNotFound();
        $this->getJson('/vehicles/'.Str::uuid().'/deadlines')->assertNotFound();
        $this->getJson('/vehicles/not-a-uuid/deadlines')->assertNotFound();
    }

    // ---------- GET /deadlines ----------

    public function test_pending_list_returns_only_pending_deadlines_of_every_vehicle_ordered_by_date(): void
    {
        $a = $this->vehicle(plate: 'AA-11-AA');
        $b = $this->vehicle(plate: 'BB-22-BB');
        $late = $this->deadline($a, 'IUC', '2027-06-30');
        $soon = $this->deadline($b, 'SEGURO', '2027-01-31');
        $this->deadline($a, 'SEGURO', '2026-01-31', paid: true);

        $response = $this->getJson('/deadlines')->assertOk()->assertJsonCount(2);

        $this->assertSame([$soon->id, $late->id], array_column($response->json(), 'id'));
    }

    public function test_pending_list_filters_by_type(): void
    {
        $vehicle = $this->vehicle();
        $this->deadline($vehicle, 'IUC', '2027-01-31');
        $seguro = $this->deadline($vehicle, 'SEGURO', '2027-02-28');

        $this->getJson('/deadlines?type=SEGURO')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $seguro->id);
    }

    public function test_pending_list_filters_by_due_before_inclusive(): void
    {
        $vehicle = $this->vehicle();
        $this->deadline($vehicle, 'IUC', '2026-12-01');
        $this->deadline($vehicle, 'SEGURO', '2026-12-02');
        $this->deadline($vehicle, 'INSPECAO', '2026-12-03');

        $response = $this->getJson('/deadlines?dueBefore=2026-12-02')->assertOk()->assertJsonCount(2);

        $this->assertSame(['2026-12-01', '2026-12-02'], array_column($response->json(), 'dueDate'));
    }

    public function test_pending_list_rejects_invalid_filters(): void
    {
        foreach (['type=IPO', 'dueBefore=31-12-2026', 'dueBefore=2026-02-30'] as $query) {
            $this->getJson("/deadlines?{$query}")
                ->assertStatus(400)
                ->assertHeader('Content-Type', 'application/problem+json');
        }
    }

    public function test_pending_list_is_empty_without_deadlines(): void
    {
        $this->getJson('/deadlines')->assertOk()->assertExactJson([]);
    }
}
