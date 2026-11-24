<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Maintenance;
use App\Models\Part;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Manutenções (docs/api/sapi-vehicles.yaml): GET/POST /vehicles/{vehicleId}/maintenances e
 * GET/PATCH /vehicles/{vehicleId}/maintenances/{maintenanceId}. R7 (custo total) e R8 (peças e alertas).
 */
class MaintenancesTest extends TestCase
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

    private function vehicle(?string $userId = null, string $plate = 'AB-24-CD', int $km = 68420): Vehicle
    {
        return Vehicle::create([
            'user_id' => $userId ?? $this->user(),
            'type' => 'CARRO',
            'brand' => 'Volkswagen',
            'model' => 'Golf',
            'first_registration' => '2021-06',
            'plate' => $plate,
            'km' => $km,
        ]);
    }

    private function part(Vehicle $vehicle, string $name = 'Pneus', int $lastKm = 60000, string $lastDate = '2026-01-15'): Part
    {
        return $vehicle->parts()->create([
            'name' => $name,
            'interval_km' => 10000,
            'last_service_km' => $lastKm,
            'last_service_date' => $lastDate,
        ]);
    }

    private function openAlert(Vehicle $vehicle, Part $part): Alert
    {
        return Alert::create([
            'vehicle_id' => $vehicle->id, 'type' => 'MANUTENCAO', 'trigger' => 'KM', 'status' => 'ABERTO',
            'title' => $part->name, 'part_id' => $part->id, 'target_km' => $part->last_service_km + 10000,
        ]);
    }

    private function maintenance(Vehicle $vehicle, string $date, float $cost = 100, string $type = 'REVISAO_PERIODICA'): Maintenance
    {
        return Maintenance::create([
            'vehicle_id' => $vehicle->id, 'type' => $type, 'date' => $date, 'km' => 60000, 'cost' => $cost, 'notes' => null,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'MUDANCA_OLEO',
            'date' => '2026-11-20',
            'km' => 68000,
            'cost' => 146.5,
            'notes' => 'Oficina Bosch',
        ], $overrides);
    }

    // ---------- POST: the record ----------

    public function test_registers_a_maintenance_and_returns_201(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);

        $response = $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(), $this->headers($userId))
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('vehicleId', $vehicle->id)
            ->assertJsonPath('type', 'MUDANCA_OLEO')
            ->assertJsonPath('date', '2026-11-20')
            ->assertJsonPath('km', 68000)
            ->assertJsonPath('cost', 146.5)
            ->assertJsonPath('notes', 'Oficina Bosch')
            ->assertJsonPath('parts', [])
            ->assertJsonPath('closedAlertIds', []);

        $this->assertSame(
            ['id', 'vehicleId', 'type', 'date', 'km', 'cost', 'notes', 'parts', 'createdAt', 'closedAlertIds'],
            array_keys($response->json()),
        );
        $this->assertTrue(Str::isUuid($response->json('id')));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('createdAt'));
        $this->assertDatabaseHas('maintenances', ['id' => $response->json('id'), 'cost' => '146.50']);
    }

    public function test_a_missing_km_defaults_to_the_current_km_of_the_vehicle(): void
    {
        $vehicle = $this->vehicle(km: 68420);

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", ['type' => 'REPARACAO', 'date' => '2026-11-20', 'cost' => 50])
            ->assertCreated()
            ->assertJsonPath('km', 68420)
            ->assertJsonPath('notes', null);
    }

    public function test_a_maintenance_never_changes_the_km_of_the_vehicle(): void
    {
        $vehicle = $this->vehicle(km: 68420);

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['km' => 90000]))->assertCreated();

        $this->assertSame(68420, $vehicle->fresh()->km);
    }

    public function test_today_is_an_accepted_date(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['date' => now()->toDateString()]))->assertCreated();
    }

    // ---------- POST: parts and alerts (R8) ----------

    public function test_registering_a_part_updates_its_last_service_and_keeps_its_interval(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle, 'Pneus', lastKm: 60000, lastDate: '2026-01-15');

        $response = $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload([
            'date' => '2026-11-20', 'km' => 68000, 'cost' => 210, 'parts' => [['partId' => $part->id, 'cost' => 180]],
        ]))->assertCreated();

        $part->refresh();
        $this->assertSame(68000, $part->last_service_km);
        $this->assertSame('2026-11-20', $part->last_service_date->toDateString());
        $this->assertSame(10000, $part->interval_km);
        $this->assertSame([['partId' => $part->id, 'partName' => 'Pneus', 'cost' => 180]], $response->json('parts'));
        $this->assertDatabaseHas('maintenance_parts', ['maintenance_id' => $response->json('id'), 'part_id' => $part->id, 'vehicle_id' => $vehicle->id, 'cost' => '180.00']);
    }

    public function test_a_part_without_a_cost_is_recorded_with_a_null_cost(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => $part->id]]]))
            ->assertCreated()
            ->assertJsonPath('parts.0.cost', null);
    }

    public function test_it_closes_the_open_alert_of_each_updated_part_and_only_those(): void
    {
        $vehicle = $this->vehicle();
        $tyres = $this->part($vehicle, 'Pneus');
        $oil = $this->part($vehicle, 'Óleo e filtro de óleo');
        $brakes = $this->part($vehicle, 'Pastilhas de travão');
        $tyresAlert = $this->openAlert($vehicle, $tyres);
        $oilAlert = $this->openAlert($vehicle, $oil);
        $brakesAlert = $this->openAlert($vehicle, $brakes);

        $response = $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload([
            'parts' => [['partId' => $tyres->id], ['partId' => $oil->id]],
        ]))->assertCreated();

        $closed = $response->json('closedAlertIds');
        sort($closed);
        $expected = [$tyresAlert->id, $oilAlert->id];
        sort($expected);
        $this->assertSame($expected, $closed);

        $this->assertSame('FECHADO', $tyresAlert->fresh()->status);
        $this->assertNotNull($tyresAlert->fresh()->closed_at);
        $this->assertSame('FECHADO', $oilAlert->fresh()->status);
        $this->assertSame('ABERTO', $brakesAlert->fresh()->status); // peça não incluída
    }

    public function test_a_part_with_no_open_alert_closes_nothing(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => $part->id]]]))
            ->assertCreated()
            ->assertJsonPath('closedAlertIds', []);
    }

    public function test_an_alert_that_was_already_closed_is_not_touched(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);
        $closedAt = now()->subDays(3);
        $alert = Alert::create([
            'vehicle_id' => $vehicle->id, 'type' => 'MANUTENCAO', 'trigger' => 'KM', 'status' => 'FECHADO',
            'title' => 'Pneus', 'part_id' => $part->id, 'target_km' => 70000, 'closed_at' => $closedAt,
        ]);

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => $part->id]]]))
            ->assertCreated()
            ->assertJsonPath('closedAlertIds', []);

        $this->assertEquals($closedAt->timestamp, $alert->fresh()->closed_at->timestamp);
    }

    public function test_a_maintenance_older_than_the_last_service_is_recorded_but_does_not_move_the_part_back(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle, 'Pneus', lastKm: 60000, lastDate: '2026-06-01');
        $alert = $this->openAlert($vehicle, $part);

        $response = $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload([
            'date' => '2026-03-01', 'km' => 55000, 'parts' => [['partId' => $part->id]],
        ]))->assertCreated();

        $this->assertDatabaseHas('maintenances', ['id' => $response->json('id')]);
        $this->assertSame(60000, $part->fresh()->last_service_km);
        $this->assertSame('2026-06-01', $part->fresh()->last_service_date->toDateString());
        $this->assertSame('ABERTO', $alert->fresh()->status);
        $response->assertJsonPath('closedAlertIds', []);
    }

    public function test_a_maintenance_on_the_same_day_as_the_last_service_updates_the_part(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle, 'Pneus', lastKm: 60000, lastDate: '2026-11-20');

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['date' => '2026-11-20', 'km' => 61000, 'parts' => [['partId' => $part->id]]]))
            ->assertCreated();

        $this->assertSame(61000, $part->fresh()->last_service_km);
    }

    public function test_a_part_of_another_vehicle_is_a_400_and_nothing_is_saved(): void
    {
        $vehicle = $this->vehicle(plate: 'AA-11-AA');
        $mine = $this->part($vehicle, 'Pneus');
        $foreign = $this->part($this->vehicle(plate: 'BB-22-BB'), 'Pneus');

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload([
            'parts' => [['partId' => $mine->id], ['partId' => $foreign->id]],
        ]))
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('errors.0.field', 'parts.1.partId');

        // Transaction: not even the valid part or the maintenance itself were saved.
        $this->assertSame(0, $vehicle->maintenances()->count());
        $this->assertSame(60000, $mine->fresh()->last_service_km);
        $this->assertSame(0, DB::table('maintenance_parts')->count());
    }

    public function test_a_part_id_in_upper_case_is_accepted(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle, 'Pneus', lastKm: 60000, lastDate: '2026-01-15');

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => strtoupper($part->id)]]]))
            ->assertCreated()
            ->assertJsonPath('parts.0.partId', $part->id);

        $this->assertSame(68000, $part->fresh()->last_service_km);
    }

    public function test_the_same_part_in_different_cases_counts_as_repeated(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);

        $response = $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload([
            'parts' => [['partId' => $part->id], ['partId' => strtoupper($part->id)]],
        ]))->assertStatus(400);

        // The "distinct" rule flags every position that is repeated.
        $this->assertContains('parts.1.partId', array_column($response->json('errors'), 'field'));
        $this->assertSame(0, $vehicle->maintenances()->count());
    }

    public function test_an_unknown_part_is_a_400(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => (string) Str::uuid()]]]))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'parts.0.partId');
    }

    public function test_a_repeated_or_malformed_part_id_is_a_400(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => $part->id], ['partId' => $part->id]]]))
            ->assertStatus(400);
        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => 'abc']]]))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'parts.0.partId');
        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['cost' => 5]]]))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'parts.0.partId');

        $this->assertSame(0, $vehicle->maintenances()->count());
    }

    // ---------- POST: validation ----------

    public function test_create_reports_every_invalid_field_by_name(): void
    {
        $vehicle = $this->vehicle();

        $response = $this->postJson("/vehicles/{$vehicle->id}/maintenances", [
            'type' => 'LAVAGEM',
            'date' => '20/11/2026',
            'km' => -5,
            'cost' => -1,
            'notes' => str_repeat('a', 1001),
        ])->assertStatus(400)->assertHeader('Content-Type', 'application/problem+json');

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['cost', 'date', 'km', 'notes', 'type'], $fields);
    }

    public function test_create_requires_type_date_and_cost(): void
    {
        $vehicle = $this->vehicle();

        $response = $this->postJson("/vehicles/{$vehicle->id}/maintenances", [])->assertStatus(400);

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['cost', 'date', 'type'], $fields);
    }

    public function test_a_future_date_is_rejected(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['date' => now()->addDay()->toDateString()]))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'date');
    }

    public function test_costs_accept_at_most_two_decimals(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['cost' => 10.999]))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'cost');
        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => $part->id, 'cost' => 1.234]]]))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'parts.0.cost');
        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['cost' => 0]))->assertCreated();
    }

    public function test_every_maintenance_type_is_accepted(): void
    {
        $vehicle = $this->vehicle();

        foreach (['REVISAO_PERIODICA', 'MUDANCA_OLEO', 'SUBSTITUICAO_PNEUS', 'REPARACAO', 'AVARIA', 'OUTRO'] as $type) {
            $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['type' => $type]))->assertCreated();
        }
    }

    public function test_create_on_another_users_or_unknown_vehicle_is_404(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(), $this->headers($this->user()))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
        $this->postJson('/vehicles/'.Str::uuid().'/maintenances', $this->payload())->assertNotFound();
        $this->postJson('/vehicles/not-a-uuid/maintenances', $this->payload())->assertNotFound();

        $this->assertSame(0, $vehicle->maintenances()->count());
    }

    public function test_create_rejects_a_header_that_is_not_a_uuid(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(), ['X-User-Id' => 'abc'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'X-User-Id');
    }

    // ---------- GET list / show ----------

    public function test_list_returns_the_history_of_the_vehicle_most_recent_first_with_parts(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $old = $this->maintenance($vehicle, '2025-12-10', 285);
        $recent = $this->maintenance($vehicle, '2026-10-18', 146);
        $part = $this->part($vehicle, 'Pneus');
        $recent->parts()->attach($part->id, ['vehicle_id' => $vehicle->id, 'cost' => 60]);
        $this->maintenance($this->vehicle(plate: 'ZZ-99-ZZ'), '2026-11-01'); // outro veículo

        $response = $this->getJson("/vehicles/{$vehicle->id}/maintenances", $this->headers($userId))
            ->assertOk()
            ->assertJsonCount(2);

        $this->assertSame([$recent->id, $old->id], array_column($response->json(), 'id'));
        $this->assertSame([['partId' => $part->id, 'partName' => 'Pneus', 'cost' => 60]], $response->json('0.parts'));
        $this->assertSame([], $response->json('1.parts'));
        $this->assertSame(146, $response->json('0.cost'));
    }

    public function test_list_is_empty_for_a_vehicle_without_maintenances(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/maintenances")->assertOk()->assertExactJson([]);
    }

    public function test_list_of_another_users_or_unknown_vehicle_is_404(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/maintenances", $this->headers($this->user()))->assertNotFound();
        $this->getJson('/vehicles/'.Str::uuid().'/maintenances')->assertNotFound();
        $this->getJson('/vehicles/not-a-uuid/maintenances')->assertNotFound();
    }

    public function test_show_returns_one_maintenance(): void
    {
        $vehicle = $this->vehicle();
        $maintenance = $this->maintenance($vehicle, '2026-10-18', 146);

        $this->getJson("/vehicles/{$vehicle->id}/maintenances/{$maintenance->id}")
            ->assertOk()
            ->assertJsonPath('id', $maintenance->id)
            ->assertJsonPath('cost', 146)
            ->assertJsonPath('parts', []);
    }

    public function test_show_is_404_for_a_maintenance_of_another_vehicle_user_or_unknown(): void
    {
        $vehicle = $this->vehicle(plate: 'AA-11-AA');
        $other = $this->vehicle(plate: 'BB-22-BB');
        $foreign = $this->maintenance($other, '2026-10-18');
        $mine = $this->maintenance($vehicle, '2026-10-18');

        $this->getJson("/vehicles/{$vehicle->id}/maintenances/{$foreign->id}")->assertNotFound();
        $this->getJson("/vehicles/{$vehicle->id}/maintenances/".Str::uuid())->assertNotFound();
        $this->getJson("/vehicles/{$vehicle->id}/maintenances/not-a-uuid")->assertNotFound();
        $this->getJson("/vehicles/{$vehicle->id}/maintenances/{$mine->id}", $this->headers($this->user()))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    // ---------- PATCH ----------

    public function test_patch_corrects_cost_and_notes_only(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);
        $maintenance = $this->maintenance($vehicle, '2026-10-18', 146);
        $maintenance->parts()->attach($part->id, ['vehicle_id' => $vehicle->id, 'cost' => 60]);

        $this->patchJson("/vehicles/{$vehicle->id}/maintenances/{$maintenance->id}", [
            'cost' => 160.75, 'notes' => 'Fatura corrigida', 'type' => 'AVARIA', 'date' => '2020-01-01', 'km' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('cost', 160.75)
            ->assertJsonPath('notes', 'Fatura corrigida')
            ->assertJsonPath('type', 'REVISAO_PERIODICA')
            ->assertJsonPath('date', '2026-10-18')
            ->assertJsonPath('km', 60000)
            ->assertJsonPath('parts.0.partId', $part->id)
            ->assertJsonPath('parts.0.cost', 60);
    }

    public function test_patch_can_change_only_one_field_and_clear_the_notes(): void
    {
        $vehicle = $this->vehicle();
        $maintenance = $this->maintenance($vehicle, '2026-10-18', 146);
        $maintenance->update(['notes' => 'antiga']);

        $this->patchJson("/vehicles/{$vehicle->id}/maintenances/{$maintenance->id}", ['cost' => 99])
            ->assertOk()
            ->assertJsonPath('cost', 99)
            ->assertJsonPath('notes', 'antiga');
        $this->patchJson("/vehicles/{$vehicle->id}/maintenances/{$maintenance->id}", ['notes' => null])
            ->assertOk()
            ->assertJsonPath('notes', null)
            ->assertJsonPath('cost', 99);
    }

    public function test_patch_requires_cost_or_notes_and_validates_them(): void
    {
        $vehicle = $this->vehicle();
        $maintenance = $this->maintenance($vehicle, '2026-10-18');
        $url = "/vehicles/{$vehicle->id}/maintenances/{$maintenance->id}";

        $this->patchJson($url, [])->assertStatus(400)->assertHeader('Content-Type', 'application/problem+json');
        $this->patchJson($url, ['km' => 1])->assertStatus(400);
        $this->patchJson($url, ['cost' => -1])->assertStatus(400)->assertJsonPath('errors.0.field', 'cost');
        $this->patchJson($url, ['cost' => null])->assertStatus(400)->assertJsonPath('errors.0.field', 'cost');
        $this->patchJson($url, ['notes' => str_repeat('a', 1001)])->assertStatus(400)->assertJsonPath('errors.0.field', 'notes');
    }

    public function test_patch_of_another_users_or_unknown_maintenance_is_404_and_changes_nothing(): void
    {
        $vehicle = $this->vehicle();
        $maintenance = $this->maintenance($vehicle, '2026-10-18', 146);

        $this->patchJson("/vehicles/{$vehicle->id}/maintenances/{$maintenance->id}", ['cost' => 1], $this->headers($this->user()))->assertNotFound();
        $this->patchJson("/vehicles/{$vehicle->id}/maintenances/".Str::uuid(), ['cost' => 1])->assertNotFound();
        $this->patchJson("/vehicles/{$vehicle->id}/maintenances/not-a-uuid", ['cost' => 1])->assertNotFound();

        $this->assertDatabaseHas('maintenances', ['id' => $maintenance->id, 'cost' => '146.00']);
    }

    public function test_deleting_the_vehicle_deletes_its_maintenances(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);
        $this->postJson("/vehicles/{$vehicle->id}/maintenances", $this->payload(['parts' => [['partId' => $part->id]]]))->assertCreated();

        $this->deleteJson("/vehicles/{$vehicle->id}")->assertNoContent();

        $this->assertSame(0, DB::table('maintenances')->count());
        $this->assertSame(0, DB::table('maintenance_parts')->count());
    }
}
