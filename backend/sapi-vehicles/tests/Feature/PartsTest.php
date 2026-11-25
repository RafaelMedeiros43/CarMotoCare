<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /vehicles/{vehicleId}/parts: peças do plano com os valores calculados (D6; docs/data/data-model.md, secção 4).
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
}
