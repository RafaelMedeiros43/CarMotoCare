<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /vehicles/{vehicleId}/expenses: a view (D5) sobre manutenções, inspeções e prazos pagos (IUC e seguro).
 */
class ExpensesTest extends TestCase
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

    private function maintenance(Vehicle $vehicle, string $date, float $cost, ?string $notes = null, int $km = 60000): Maintenance
    {
        return Maintenance::create([
            'vehicle_id' => $vehicle->id, 'type' => 'REVISAO_PERIODICA', 'date' => $date, 'km' => $km, 'cost' => $cost, 'notes' => $notes,
        ]);
    }

    private function paidDeadline(Vehicle $vehicle, string $type, string $paidAt, float $amount, ?string $description = null): Deadline
    {
        return Deadline::create([
            'vehicle_id' => $vehicle->id, 'type' => $type, 'status' => 'CONCLUIDO', 'due_date' => '2026-01-31',
            'paid_at' => $paidAt, 'amount' => $amount, 'description' => $description,
        ]);
    }

    public function test_lists_maintenances_inspections_and_paid_iuc_and_seguro_most_recent_first(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $maintenance = $this->maintenance($vehicle, '2026-10-18', 146, 'Oficina Bosch', 67440);
        $iuc = $this->paidDeadline($vehicle, 'IUC', '2026-01-20', 51.5, 'IUC 2026');
        $seguro = $this->paidDeadline($vehicle, 'SEGURO', '2026-05-02', 320);
        $inspectionId = (string) Str::uuid();
        DB::table('inspections')->insert([
            'id' => $inspectionId, 'vehicle_id' => $vehicle->id, 'date' => '2026-07-09', 'result' => 'APROVADA',
            'amount' => 35, 'km' => 65000, 'valid_until' => '2028-07-09',
        ]);

        $response = $this->getJson("/vehicles/{$vehicle->id}/expenses", $this->headers($userId))
            ->assertOk()
            ->assertJsonCount(4);

        $this->assertSame([$maintenance->id, $inspectionId, $seguro->id, $iuc->id], array_column($response->json(), 'id'));
        $this->assertSame(['MANUTENCAO', 'INSPECAO', 'SEGURO', 'IUC'], array_column($response->json(), 'category'));
        $this->assertSame(['2026-10-18', '2026-07-09', '2026-05-02', '2026-01-20'], array_column($response->json(), 'date'));
    }

    private function headers(string $userId): array
    {
        return ['X-User-Id' => $userId];
    }

    public function test_each_item_has_the_contract_fields_with_nulls_where_they_do_not_apply(): void
    {
        $vehicle = $this->vehicle();
        $this->maintenance($vehicle, '2026-10-18', 146.5, 'Oficina Bosch', 67440);
        $this->paidDeadline($vehicle, 'IUC', '2026-01-20', 51.5, 'IUC 2026');

        $response = $this->getJson("/vehicles/{$vehicle->id}/expenses")->assertOk();

        $this->assertSame(['id', 'category', 'date', 'amount', 'description', 'km'], array_keys($response->json('0')));
        // Manutenção: o custo total, as notas como descrição e a km.
        $this->assertSame('MANUTENCAO', $response->json('0.category'));
        $this->assertSame(146.5, $response->json('0.amount'));
        $this->assertSame('Oficina Bosch', $response->json('0.description'));
        $this->assertSame(67440, $response->json('0.km'));
        // Prazo pago: sem km.
        $this->assertSame('IUC', $response->json('1.category'));
        $this->assertSame(51.5, $response->json('1.amount'));
        $this->assertSame('IUC 2026', $response->json('1.description'));
        $this->assertNull($response->json('1.km'));
    }

    public function test_amounts_are_json_numbers_not_text(): void
    {
        $vehicle = $this->vehicle();
        $this->maintenance($vehicle, '2026-10-18', 146);

        $amount = $this->getJson("/vehicles/{$vehicle->id}/expenses")->json('0.amount');

        $this->assertIsNotString($amount);
        $this->assertEquals(146, $amount);
    }

    public function test_pending_deadlines_and_other_vehicles_are_left_out(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $other = $this->vehicle(plate: 'ZZ-99-ZZ');
        $mine = $this->maintenance($vehicle, '2026-10-18', 146);
        Deadline::create(['vehicle_id' => $vehicle->id, 'type' => 'IUC', 'status' => 'PENDENTE', 'due_date' => '2027-01-31']);
        $this->maintenance($other, '2026-11-01', 999);
        $this->paidDeadline($other, 'SEGURO', '2026-11-02', 500);

        $response = $this->getJson("/vehicles/{$vehicle->id}/expenses", $this->headers($userId))->assertOk()->assertJsonCount(1);

        $this->assertSame($mine->id, $response->json('0.id'));
    }

    public function test_a_paid_inspection_deadline_is_not_an_expense_of_its_own(): void
    {
        // O custo da inspeção está em inspections; o prazo INSPECAO pago não conta (evita contar duas vezes).
        $vehicle = $this->vehicle();
        $this->paidDeadline($vehicle, 'INSPECAO', '2026-07-09', 35);

        $this->getJson("/vehicles/{$vehicle->id}/expenses")->assertOk()->assertExactJson([]);
    }

    public function test_a_corrected_maintenance_cost_shows_up_in_the_expenses(): void
    {
        $vehicle = $this->vehicle();
        $maintenance = $this->maintenance($vehicle, '2026-10-18', 146);

        $this->patchJson("/vehicles/{$vehicle->id}/maintenances/{$maintenance->id}", ['cost' => 160, 'notes' => 'Corrigido'])->assertOk();

        $this->getJson("/vehicles/{$vehicle->id}/expenses")
            ->assertJsonPath('0.amount', 160)
            ->assertJsonPath('0.description', 'Corrigido');
    }

    public function test_a_new_maintenance_appears_in_the_expenses(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", ['type' => 'AVARIA', 'date' => '2026-11-20', 'km' => 68000, 'cost' => 75])->assertCreated();

        $this->getJson("/vehicles/{$vehicle->id}/expenses")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.category', 'MANUTENCAO')
            ->assertJsonPath('0.amount', 75)
            ->assertJsonPath('0.km', 68000);
    }

    public function test_is_empty_for_a_vehicle_without_expenses(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/expenses")->assertOk()->assertExactJson([]);
    }

    public function test_of_another_users_or_unknown_vehicle_is_404(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/expenses", $this->headers($this->user()))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
        $this->getJson('/vehicles/'.Str::uuid().'/expenses')->assertNotFound();
        $this->getJson('/vehicles/not-a-uuid/expenses')->assertNotFound();
    }
}
