<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Part;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Peças do plano (docs/api/sapi-vehicles.yaml):
 * GET /vehicles/{vehicleId}/parts devolve-as com os valores calculados (D6; docs/data/data-model.md, secção 4);
 * PATCH /vehicles/{vehicleId}/parts/{partId} altera o intervalo e/ou a referência (último serviço).
 */
class PartsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Data fixa: nextDate e daysRemaining dependem do dia de hoje.
        $this->travelTo('2026-11-25 12:00:00');
    }

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

    private function vehicle(?string $userId = null, string $plate = 'AB-24-CD', int $km = 60000): Vehicle
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

    private function part(Vehicle $vehicle, array $overrides = []): Part
    {
        return $vehicle->parts()->create(array_merge([
            'name' => 'Óleo e filtro de óleo',
            'interval_km' => 10000,
            'interval_months' => null,
            'interval_source' => 'CATALOGO',
            'last_service_km' => 55000,
            'last_service_date' => '2026-06-10',
        ], $overrides));
    }

    private function headers(string $userId): array
    {
        return ['X-User-Id' => $userId];
    }

    public function test_each_part_has_the_contract_fields(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $part = $this->part($vehicle, ['interval_months' => 12, 'interval_source' => 'UTILIZADOR']);

        $this->getJson("/vehicles/{$vehicle->id}/parts", $this->headers($userId))
            ->assertOk()
            ->assertExactJson([[
                'id' => $part->id,
                'vehicleId' => $vehicle->id,
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
            ]]);
    }

    public function test_an_overdue_part_has_negative_remaining_values(): void
    {
        $vehicle = $this->vehicle(km: 66000);
        $this->part($vehicle, ['interval_months' => 12, 'last_service_date' => '2025-01-01']);

        $this->getJson("/vehicles/{$vehicle->id}/parts")
            ->assertOk()
            ->assertJsonPath('0.nextKm', 65000)
            ->assertJsonPath('0.kmRemaining', -1000)
            ->assertJsonPath('0.nextDate', '2026-01-01')
            ->assertJsonPath('0.daysRemaining', -328);
    }

    public function test_a_part_due_today_has_zero_days_remaining(): void
    {
        $vehicle = $this->vehicle();
        $this->part($vehicle, ['interval_months' => 6, 'last_service_date' => '2026-05-25']);

        $this->getJson("/vehicles/{$vehicle->id}/parts")
            ->assertOk()
            ->assertJsonPath('0.nextDate', '2026-11-25')
            ->assertJsonPath('0.daysRemaining', 0);
    }

    public function test_next_date_does_not_overflow_into_the_following_month(): void
    {
        $vehicle = $this->vehicle();
        $this->part($vehicle, ['interval_months' => 1, 'last_service_date' => '2026-01-31']);

        $this->getJson("/vehicles/{$vehicle->id}/parts")
            ->assertOk()
            ->assertJsonPath('0.nextDate', '2026-02-28');
    }

    public function test_without_a_months_interval_the_date_values_are_null(): void
    {
        $vehicle = $this->vehicle();
        $this->part($vehicle);

        $this->getJson("/vehicles/{$vehicle->id}/parts")
            ->assertOk()
            ->assertJsonPath('0.intervalMonths', null)
            ->assertJsonPath('0.nextDate', null)
            ->assertJsonPath('0.daysRemaining', null)
            ->assertJsonPath('0.nextKm', 65000);
    }

    public function test_without_a_km_interval_the_km_values_are_null(): void
    {
        $vehicle = $this->vehicle();
        $this->part($vehicle, ['interval_km' => null, 'interval_months' => 12]);

        $this->getJson("/vehicles/{$vehicle->id}/parts")
            ->assertOk()
            ->assertJsonPath('0.intervalKm', null)
            ->assertJsonPath('0.nextKm', null)
            ->assertJsonPath('0.kmRemaining', null)
            ->assertJsonPath('0.nextDate', '2027-06-10');
    }

    public function test_parts_come_in_the_order_of_the_default_plan(): void
    {
        $userId = $this->user();

        foreach (['CARRO' => 'AB-24-CD', 'MOTO' => '45-XY-12'] as $type => $plate) {
            $id = $this->postJson('/vehicles', [
                'type' => $type,
                'brand' => 'Marca',
                'model' => 'Modelo',
                'firstRegistration' => '2021-06',
                'plate' => $plate,
                'km' => 60000,
            ], $this->headers($userId))->assertCreated()->json('id');

            $response = $this->getJson("/vehicles/{$id}/parts", $this->headers($userId))->assertOk();

            $this->assertSame(
                array_column(config("default_parts.{$type}"), 'name'),
                array_column($response->json(), 'name'),
            );
        }
    }

    public function test_parts_of_other_vehicles_are_left_out(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $mine = $this->part($vehicle);
        $this->part($this->vehicle($userId, 'ZZ-99-ZZ'));

        $this->getJson("/vehicles/{$vehicle->id}/parts", $this->headers($userId))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);
    }

    public function test_is_empty_for_a_vehicle_without_parts(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/parts")->assertOk()->assertExactJson([]);
    }

    public function test_of_another_users_vehicle_is_404(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);

        // O dono vê o veículo: assim o 404 seguinte não vem de a rota não existir.
        $this->getJson("/vehicles/{$vehicle->id}/parts", $this->headers($userId))->assertOk();

        $this->getJson("/vehicles/{$vehicle->id}/parts", $this->headers($this->user()))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_of_an_unknown_or_malformed_id_is_404(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/parts")->assertOk();

        $this->getJson('/vehicles/'.Str::uuid().'/parts')->assertNotFound();
        $this->getJson('/vehicles/not-a-uuid/parts')->assertNotFound();
    }

    public function test_a_user_id_header_that_is_not_a_uuid_is_400(): void
    {
        $vehicle = $this->vehicle();

        $this->getJson("/vehicles/{$vehicle->id}/parts", $this->headers('abc'))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'X-User-Id');
    }

    // ---------- PATCH /vehicles/{vehicleId}/parts/{partId} ----------

    private function patchPart(Part $part, array $body, array $headers = []): TestResponse
    {
        return $this->patchJson("/vehicles/{$part->vehicle_id}/parts/{$part->id}", $body, $headers);
    }

    public function test_update_changes_the_interval_and_returns_the_recalculated_part(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $part = $this->part($vehicle);

        $this->patchPart($part, ['intervalKm' => 15000, 'intervalMonths' => 12], $this->headers($userId))
            ->assertOk()
            ->assertExactJson([
                'id' => $part->id,
                'vehicleId' => $vehicle->id,
                'name' => 'Óleo e filtro de óleo',
                'intervalKm' => 15000,
                'intervalMonths' => 12,
                'intervalSource' => 'UTILIZADOR',
                'lastServiceKm' => 55000,
                'lastServiceDate' => '2026-06-10',
                'nextKm' => 70000,
                'nextDate' => '2027-06-10',
                'kmRemaining' => 10000,
                'daysRemaining' => 197,
            ]);

        $this->assertDatabaseHas('parts', [
            'id' => $part->id,
            'interval_km' => 15000,
            'interval_months' => 12,
            'interval_source' => 'UTILIZADOR',
        ]);
    }

    public function test_update_changes_the_last_service_and_keeps_the_source(): void
    {
        $part = $this->part($this->vehicle());

        $this->patchPart($part, ['lastServiceKm' => 50000, 'lastServiceDate' => '2026-03-01'])
            ->assertOk()
            ->assertJsonPath('lastServiceKm', 50000)
            ->assertJsonPath('lastServiceDate', '2026-03-01')
            ->assertJsonPath('nextKm', 60000)
            ->assertJsonPath('kmRemaining', 0)
            ->assertJsonPath('intervalKm', 10000)
            ->assertJsonPath('intervalSource', 'CATALOGO');

        $this->assertDatabaseHas('parts', [
            'id' => $part->id,
            'last_service_km' => 50000,
            'last_service_date' => '2026-03-01',
        ]);
    }

    public function test_update_accepts_each_reference_field_on_its_own(): void
    {
        $part = $this->part($this->vehicle());

        $this->patchPart($part, ['lastServiceKm' => 58000])
            ->assertOk()
            ->assertJsonPath('lastServiceKm', 58000)
            ->assertJsonPath('lastServiceDate', '2026-06-10');

        $this->patchPart($part, ['lastServiceDate' => '2026-07-01'])
            ->assertOk()
            ->assertJsonPath('lastServiceKm', 58000)
            ->assertJsonPath('lastServiceDate', '2026-07-01');
    }

    public function test_sending_the_current_interval_keeps_the_source(): void
    {
        $part = $this->part($this->vehicle());

        $this->patchPart($part, ['intervalKm' => 10000, 'intervalMonths' => null])
            ->assertOk()
            ->assertJsonPath('intervalSource', 'CATALOGO');
    }

    public function test_a_part_defined_by_the_user_never_goes_back_to_the_catalogue(): void
    {
        $part = $this->part($this->vehicle(), ['interval_source' => 'UTILIZADOR']);

        $this->patchPart($part, ['intervalKm' => 10000, 'lastServiceKm' => 56000])
            ->assertOk()
            ->assertJsonPath('intervalSource', 'UTILIZADOR');
    }

    public function test_null_disables_a_limit_while_the_other_stays(): void
    {
        $part = $this->part($this->vehicle(), ['interval_months' => 12]);

        $this->patchPart($part, ['intervalKm' => null])
            ->assertOk()
            ->assertJsonPath('intervalKm', null)
            ->assertJsonPath('nextKm', null)
            ->assertJsonPath('kmRemaining', null)
            ->assertJsonPath('intervalMonths', 12)
            ->assertJsonPath('intervalSource', 'UTILIZADOR');
    }

    public function test_one_limit_can_be_swapped_for_the_other_in_the_same_request(): void
    {
        $part = $this->part($this->vehicle());

        $this->patchPart($part, ['intervalKm' => null, 'intervalMonths' => 24])
            ->assertOk()
            ->assertJsonPath('intervalKm', null)
            ->assertJsonPath('intervalMonths', 24)
            ->assertJsonPath('nextDate', '2028-06-10');
    }

    public function test_update_accepts_the_extreme_values(): void
    {
        $part = $this->part($this->vehicle());

        $this->patchPart($part, [
            'intervalKm' => 1,
            'intervalMonths' => 999,
            'lastServiceKm' => 60000, // a km atual do veículo
            'lastServiceDate' => '2026-11-25', // hoje
        ])->assertOk();

        $this->patchPart($part, ['lastServiceKm' => 0])->assertOk()->assertJsonPath('lastServiceKm', 0);
    }

    public function test_a_stored_reference_above_the_current_km_does_not_block_an_interval_change(): void
    {
        // Possível hoje: uma manutenção pode ser registada com mais km do que o veículo.
        $part = $this->part($this->vehicle(), ['last_service_km' => 70000]);

        $this->patchPart($part, ['intervalKm' => 15000])
            ->assertOk()
            ->assertJsonPath('lastServiceKm', 70000);
    }

    public function test_update_ignores_fields_that_cannot_be_changed(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);

        $this->patchPart($part, [
            'intervalKm' => 12000,
            'name' => 'Outra peça',
            'intervalSource' => 'CATALOGO',
            'vehicleId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Óleo e filtro de óleo')
            ->assertJsonPath('vehicleId', $vehicle->id)
            ->assertJsonPath('intervalSource', 'UTILIZADOR');
    }

    public function test_update_changes_nothing_else(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);
        $other = $this->part($vehicle, ['name' => 'Filtro de ar']);
        $alert = Alert::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'MANUTENCAO',
            'trigger' => 'KM',
            'status' => 'ABERTO',
            'title' => 'Óleo e filtro de óleo',
            'part_id' => $part->id,
            'target_km' => 65000,
        ]);

        $this->patchPart($part, ['intervalKm' => 20000, 'lastServiceKm' => 59000])->assertOk();

        $this->assertSame(60000, $vehicle->fresh()->km);
        $this->assertSame(10000, $other->fresh()->interval_km);
        $this->assertSame(55000, $other->fresh()->last_service_km);
        $this->assertSame('ABERTO', $alert->fresh()->status); // R9: só a manutenção fecha o alerta
        $this->assertDatabaseCount('maintenances', 0);
    }

    public function test_update_without_any_field_to_change_is_400(): void
    {
        $part = $this->part($this->vehicle());

        foreach ([[], ['name' => 'Outra peça']] as $body) {
            $this->patchPart($part, $body)
                ->assertStatus(400)
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('errors.0.field', 'body');
        }
    }

    public function test_update_cannot_leave_a_part_without_any_limit(): void
    {
        $vehicle = $this->vehicle();
        $byKm = $this->part($vehicle);
        $both = $this->part($vehicle, ['name' => 'Filtro de ar', 'interval_months' => 12]);

        $this->patchPart($byKm, ['intervalKm' => null])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'body');

        $this->patchPart($both, ['intervalKm' => null, 'intervalMonths' => null])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'body');

        $this->assertSame(10000, $byKm->fresh()->interval_km);
        $this->assertSame(12, $both->fresh()->interval_months);
    }

    public function test_update_rejects_invalid_values_naming_the_field(): void
    {
        $part = $this->part($this->vehicle());

        $cases = [
            ['intervalKm', 0],
            ['intervalKm', -1],
            ['intervalKm', 1.5],
            ['intervalKm', 'dez mil'],
            ['intervalKm', 2147483648], // não cabe na coluna INTEGER
            ['intervalMonths', 0],
            ['intervalMonths', 1000],
            ['intervalMonths', 1.5],
            ['lastServiceKm', -1],
            ['lastServiceKm', 60001], // acima da km atual do veículo
            ['lastServiceKm', null],
            ['lastServiceDate', '2026-11-26'], // amanhã
            ['lastServiceDate', '10-06-2026'],
            ['lastServiceDate', '2026-02-30'],
            ['lastServiceDate', null],
        ];

        foreach ($cases as [$field, $value]) {
            $response = $this->patchPart($part, [$field => $value]);

            $this->assertSame(
                [400, $field],
                [$response->status(), $response->json('errors.0.field')],
                "{$field} = ".var_export($value, true),
            );
        }
    }

    public function test_update_saves_nothing_when_one_field_is_invalid(): void
    {
        $part = $this->part($this->vehicle());

        $this->patchPart($part, ['intervalKm' => 15000, 'lastServiceKm' => 60001])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'lastServiceKm');

        $this->assertSame(10000, $part->fresh()->interval_km);
        $this->assertSame('CATALOGO', $part->fresh()->interval_source);
    }

    public function test_update_of_another_users_vehicle_is_404(): void
    {
        $userId = $this->user();
        $part = $this->part($this->vehicle($userId));

        // O dono altera a peça: assim o 404 seguinte não vem de a rota não existir.
        $this->patchPart($part, ['intervalKm' => 15000], $this->headers($userId))->assertOk();

        $this->patchPart($part, ['intervalKm' => 20000], $this->headers($this->user()))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertSame(15000, $part->fresh()->interval_km);
    }

    public function test_update_of_a_part_of_another_vehicle_is_404(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $part = $this->part($vehicle);
        $otherPart = $this->part($this->vehicle($userId, 'ZZ-99-ZZ'));

        $this->patchPart($part, ['intervalKm' => 15000], $this->headers($userId))->assertOk();

        $this->patchJson("/vehicles/{$vehicle->id}/parts/{$otherPart->id}", ['intervalKm' => 15000], $this->headers($userId))
            ->assertNotFound();

        $this->assertSame(10000, $otherPart->fresh()->interval_km);
    }

    public function test_update_of_an_unknown_or_malformed_id_is_404(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);
        $body = ['intervalKm' => 15000];

        $this->patchPart($part, $body)->assertOk();

        $this->patchJson("/vehicles/{$vehicle->id}/parts/".Str::uuid(), $body)->assertNotFound();
        $this->patchJson("/vehicles/{$vehicle->id}/parts/not-a-uuid", $body)->assertNotFound();
        $this->patchJson('/vehicles/'.Str::uuid()."/parts/{$part->id}", $body)->assertNotFound();
        $this->patchJson("/vehicles/not-a-uuid/parts/{$part->id}", $body)->assertNotFound();
    }

    public function test_update_with_a_user_id_header_that_is_not_a_uuid_is_400(): void
    {
        $part = $this->part($this->vehicle());

        $this->patchPart($part, ['intervalKm' => 15000], $this->headers('abc'))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'X-User-Id');
    }
}
