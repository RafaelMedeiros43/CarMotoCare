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
 * PUT /vehicles/{vehicleId}/mileage (docs/api/sapi-vehicles.yaml), usado pelo Microserviço OBD.
 * A km nunca diminui (R3). nextAlertKm é o menor nextKm das peças com limite em km e sem alerta aberto
 * (docs/data/data-model.md, secção 4).
 */
class MileageTest extends TestCase
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

    /** A part whose next service is at $nextKm (interval of 10 000 km). */
    private function part(Vehicle $vehicle, string $name, int $nextKm): Part
    {
        return $vehicle->parts()->create([
            'name' => $name,
            'interval_km' => 10000,
            'interval_months' => null,
            'interval_source' => 'CATALOGO',
            'last_service_km' => $nextKm - 10000,
            'last_service_date' => '2026-06-10',
        ]);
    }

    private function alert(Part $part, array $overrides = []): Alert
    {
        return Alert::create(array_merge([
            'vehicle_id' => $part->vehicle_id,
            'type' => 'MANUTENCAO',
            'trigger' => 'KM',
            'status' => 'ABERTO',
            'title' => $part->name,
            'part_id' => $part->id,
            'target_km' => $part->nextKm(),
        ], $overrides));
    }

    private function putMileage(Vehicle $vehicle, array $body, array $headers = []): TestResponse
    {
        return $this->putJson("/vehicles/{$vehicle->id}/mileage", $body, $headers);
    }

    public function test_saves_the_new_km_and_returns_the_result(): void
    {
        $vehicle = $this->vehicle();
        $this->part($vehicle, 'Óleo e filtro de óleo', 65000);

        $this->putMileage($vehicle, ['km' => 61000])
            ->assertOk()
            ->assertExactJson([
                'vehicleId' => $vehicle->id,
                'km' => 61000,
                'previousKm' => 60000,
                'nextAlertKm' => 65000,
            ]);

        $this->assertSame(61000, $vehicle->fresh()->km);
    }

    public function test_the_same_km_is_200_and_changes_nothing(): void
    {
        $vehicle = $this->vehicle();
        $before = $vehicle->fresh()->updated_at;
        $this->travel(1)->hours();

        $this->putMileage($vehicle, ['km' => 60000])
            ->assertOk()
            ->assertJsonPath('km', 60000)
            ->assertJsonPath('previousKm', 60000);

        $this->assertEquals($before, $vehicle->fresh()->updated_at);
    }

    public function test_a_lower_km_is_409_and_changes_nothing(): void
    {
        $vehicle = $this->vehicle();

        $this->putMileage($vehicle, ['km' => 59999])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertSame(60000, $vehicle->fresh()->km);
    }

    public function test_next_alert_km_is_the_smallest_next_km(): void
    {
        $vehicle = $this->vehicle();
        $this->part($vehicle, 'Óleo e filtro de óleo', 65000);
        $this->part($vehicle, 'Filtro de ar', 62000);
        $this->part($vehicle, 'Pneus', 63000);

        $this->putMileage($vehicle, ['km' => 61000])->assertOk()->assertJsonPath('nextAlertKm', 62000);
    }

    public function test_next_alert_km_ignores_parts_without_a_km_interval(): void
    {
        $vehicle = $this->vehicle();
        $this->part($vehicle, 'Óleo e filtro de óleo', 65000);
        $this->part($vehicle, 'Bateria', 61000)->update(['interval_km' => null, 'interval_months' => 12]);

        $this->putMileage($vehicle, ['km' => 61000])->assertOk()->assertJsonPath('nextAlertKm', 65000);
    }

    public function test_next_alert_km_ignores_parts_with_an_open_alert_of_any_trigger(): void
    {
        $vehicle = $this->vehicle();
        $this->alert($this->part($vehicle, 'Filtro de ar', 61500));
        $this->alert($this->part($vehicle, 'Pneus', 62000), ['trigger' => 'DATA', 'target_km' => null, 'due_date' => '2026-12-01']);
        $this->part($vehicle, 'Óleo e filtro de óleo', 65000);

        $this->putMileage($vehicle, ['km' => 61000])->assertOk()->assertJsonPath('nextAlertKm', 65000);
    }

    public function test_next_alert_km_counts_parts_whose_alert_is_already_closed(): void
    {
        $vehicle = $this->vehicle();
        $this->alert($this->part($vehicle, 'Filtro de ar', 62000), ['status' => 'FECHADO', 'closed_at' => now()]);
        $this->part($vehicle, 'Óleo e filtro de óleo', 65000);

        $this->putMileage($vehicle, ['km' => 61000])->assertOk()->assertJsonPath('nextAlertKm', 62000);
    }

    public function test_next_alert_km_of_an_overdue_part_is_below_the_current_km(): void
    {
        $vehicle = $this->vehicle();
        $this->part($vehicle, 'Óleo e filtro de óleo', 65000);

        $this->putMileage($vehicle, ['km' => 70000])->assertOk()->assertJsonPath('nextAlertKm', 65000);
    }

    public function test_next_alert_km_is_null_without_any_part_to_alert(): void
    {
        $withoutParts = $this->vehicle();

        $this->putMileage($withoutParts, ['km' => 61000])->assertOk()->assertJsonPath('nextAlertKm', null);

        $allAlerted = $this->vehicle(plate: 'ZZ-99-ZZ');
        $this->alert($this->part($allAlerted, 'Óleo e filtro de óleo', 61500));

        $this->putMileage($allAlerted, ['km' => 61000])->assertOk()->assertJsonPath('nextAlertKm', null);
    }

    public function test_changes_only_the_km_of_that_vehicle(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $part = $this->part($vehicle, 'Óleo e filtro de óleo', 61500);
        $alert = $this->alert($this->part($vehicle, 'Pneus', 61000));
        $other = $this->vehicle($userId, 'ZZ-99-ZZ');
        $this->part($other, 'Filtro de ar', 60500);

        $this->putMileage($vehicle, ['km' => 62000, 'plate' => '00-AA-00', 'userId' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('nextAlertKm', 61500); // a peça do outro veículo não conta

        $this->assertSame('AB-24-CD', $vehicle->fresh()->plate);
        $this->assertSame($userId, $vehicle->fresh()->user_id);
        $this->assertSame(60000, $other->fresh()->km);
        $this->assertSame(51500, $part->fresh()->last_service_km);
        $this->assertSame('ABERTO', $alert->fresh()->status);
        $this->assertDatabaseCount('alerts', 1); // quem cria alertas é o microserviço de alertas
        $this->assertDatabaseCount('maintenances', 0);
    }

    public function test_accepts_the_largest_km_of_the_column(): void
    {
        $this->putMileage($this->vehicle(), ['km' => 2147483647])->assertOk()->assertJsonPath('km', 2147483647);
    }

    public function test_an_invalid_km_is_400_and_changes_nothing(): void
    {
        $vehicle = $this->vehicle();

        $bodies = [
            [],
            ['km' => null],
            ['km' => -1],
            ['km' => 61000.5],
            ['km' => 'muitos'],
            ['km' => 2147483648],
        ];

        foreach ($bodies as $body) {
            $response = $this->putMileage($vehicle, $body);

            $this->assertSame(
                [400, 'km'],
                [$response->status(), $response->json('errors.0.field')],
                json_encode($body),
            );
        }

        $this->assertSame(60000, $vehicle->fresh()->km);
    }

    public function test_of_another_users_vehicle_is_404(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);

        // O dono atualiza a km: assim o 404 seguinte não vem de a rota não existir.
        $this->putMileage($vehicle, ['km' => 61000], ['X-User-Id' => $userId])->assertOk();

        $stranger = ['X-User-Id' => $this->user()];
        $this->putMileage($vehicle, ['km' => 62000], $stranger)
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
        // Mesmo com uma km inferior: não se revela nada sobre o veículo.
        $this->putMileage($vehicle, ['km' => 1], $stranger)->assertNotFound();

        $this->assertSame(61000, $vehicle->fresh()->km);
    }

    public function test_of_an_unknown_or_malformed_id_is_404(): void
    {
        $this->putMileage($this->vehicle(), ['km' => 61000])->assertOk();

        $this->putJson('/vehicles/'.Str::uuid().'/mileage', ['km' => 61000])->assertNotFound();
        $this->putJson('/vehicles/not-a-uuid/mileage', ['km' => 61000])->assertNotFound();
    }

    public function test_a_user_id_header_that_is_not_a_uuid_is_400(): void
    {
        $this->putMileage($this->vehicle(), ['km' => 61000], ['X-User-Id' => 'abc'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'X-User-Id');
    }
}
