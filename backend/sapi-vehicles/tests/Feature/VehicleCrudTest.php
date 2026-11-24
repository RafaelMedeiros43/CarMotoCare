<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CRUD de veículos (docs/api/sapi-vehicles.yaml: GET/POST /vehicles, GET/PATCH/DELETE /vehicles/{vehicleId}).
 * A tabela users só existe nos testes (tests/database/migrations): em produção é criada pela sapi-users (D3).
 */
class VehicleCrudTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'CARRO',
            'brand' => 'Volkswagen',
            'model' => 'Golf',
            'firstRegistration' => '2021-06',
            'plate' => 'AB-24-CD',
            'km' => 68420,
        ], $overrides);
    }

    private function vehicle(string $userId, array $overrides = []): Vehicle
    {
        return Vehicle::create(array_merge([
            'user_id' => $userId,
            'type' => 'CARRO',
            'brand' => 'Volkswagen',
            'model' => 'Golf',
            'first_registration' => '2021-06',
            'plate' => 'AB-24-CD',
            'km' => 68420,
        ], $overrides));
    }

    private function part(Vehicle $vehicle, string $name, ?int $intervalKm, int $lastKm, ?int $intervalMonths = null, string $lastDate = '2026-01-15')
    {
        return $vehicle->parts()->create([
            'name' => $name,
            'interval_km' => $intervalKm,
            'interval_months' => $intervalMonths,
            'last_service_km' => $lastKm,
            'last_service_date' => $lastDate,
        ]);
    }

    // ---------- POST /vehicles ----------

    public function test_creates_vehicle_and_returns_201(): void
    {
        $userId = $this->user();

        $response = $this->postJson('/vehicles', $this->payload(), $this->headers($userId))
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('userId', $userId)
            ->assertJsonPath('type', 'CARRO')
            ->assertJsonPath('brand', 'Volkswagen')
            ->assertJsonPath('model', 'Golf')
            ->assertJsonPath('firstRegistration', '2021-06')
            ->assertJsonPath('plate', 'AB-24-CD')
            ->assertJsonPath('km', 68420)
            ->assertJsonPath('openAlerts', 0);

        $this->assertSame(
            ['id', 'userId', 'type', 'brand', 'model', 'firstRegistration', 'plate', 'km', 'nextService', 'openAlerts', 'createdAt'],
            array_keys($response->json()),
        );
        $this->assertTrue(Str::isUuid($response->json('id')));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json('createdAt'));
        $this->assertDatabaseHas('vehicles', ['id' => $response->json('id'), 'user_id' => $userId, 'plate' => 'AB-24-CD']);
    }

    public function test_creating_a_car_creates_the_default_part_plan(): void
    {
        $userId = $this->user();

        $id = $this->postJson('/vehicles', $this->payload(), $this->headers($userId))->json('id');

        $parts = Vehicle::findOrFail($id)->parts;
        $this->assertCount(7, $parts);
        $this->assertContains('Filtro de habitáculo', $parts->pluck('name')->all());
        foreach ($parts as $part) {
            $this->assertSame(10000, $part->interval_km);
            $this->assertNull($part->interval_months);
            $this->assertSame('CATALOGO', $part->interval_source);
            // O último serviço é a km indicada e a data de criação: sem isto haveria alertas logo ao criar.
            $this->assertSame(68420, $part->last_service_km);
            $this->assertSame(now()->toDateString(), $part->last_service_date->toDateString());
        }
    }

    public function test_creating_a_motorcycle_creates_the_motorcycle_part_plan(): void
    {
        $userId = $this->user();

        $id = $this->postJson('/vehicles', $this->payload(['type' => 'MOTO', 'plate' => '45-XY-12']), $this->headers($userId))->json('id');

        $names = Vehicle::findOrFail($id)->parts->pluck('name')->all();
        $this->assertCount(6, $names);
        $this->assertContains('Corrente de transmissão', $names);
        $this->assertNotContains('Filtro de habitáculo', $names);
    }

    public function test_created_vehicle_has_next_service_from_the_default_plan(): void
    {
        $userId = $this->user();

        $this->postJson('/vehicles', $this->payload(['km' => 68420]), $this->headers($userId))
            ->assertJsonPath('nextService.targetKm', 78420)
            ->assertJsonPath('nextService.kmRemaining', 10000)
            ->assertJsonPath('nextService.dueDate', null);
    }

    public function test_creating_a_vehicle_does_not_create_deadlines(): void
    {
        $userId = $this->user();

        // As datas de validade são tratadas pela EAPI (POST /vehicles/{id}/deadlines); a SAPI ignora-as.
        $id = $this->postJson('/vehicles', $this->payload([
            'iucValidUntil' => '2027-01-31',
            'insuranceValidUntil' => '2027-02-28',
            'inspectionValidUntil' => '2027-03-31',
        ]), $this->headers($userId))->assertCreated()->json('id');

        $this->assertSame(0, Vehicle::findOrFail($id)->deadlines()->count());
    }

    public function test_create_requires_the_user_header(): void
    {
        $this->postJson('/vehicles', $this->payload())
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('errors.0.field', 'X-User-Id');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_create_rejects_a_header_that_is_not_a_uuid(): void
    {
        $this->postJson('/vehicles', $this->payload(), ['X-User-Id' => 'abc'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'X-User-Id');
    }

    public function test_create_reports_every_invalid_field_by_name(): void
    {
        $userId = $this->user();

        $response = $this->postJson('/vehicles', [
            'type' => 'CAMIAO',
            'brand' => '',
            'model' => str_repeat('a', 51),
            'firstRegistration' => '2021-13',
            'plate' => 'AB24CD',
            'km' => -1,
        ], $this->headers($userId))
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 400);

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['brand', 'firstRegistration', 'km', 'model', 'plate', 'type'], $fields);
        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_create_requires_every_field(): void
    {
        $userId = $this->user();

        $response = $this->postJson('/vehicles', [], $this->headers($userId))->assertStatus(400);

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['brand', 'firstRegistration', 'km', 'model', 'plate', 'type'], $fields);
    }

    public function test_create_rejects_a_lowercase_plate_and_a_decimal_km(): void
    {
        $userId = $this->user();

        $response = $this->postJson('/vehicles', $this->payload(['plate' => 'ab-24-cd', 'km' => 10.5]), $this->headers($userId))
            ->assertStatus(400);

        $fields = array_column($response->json('errors'), 'field');
        $this->assertContains('plate', $fields);
        $this->assertContains('km', $fields);
    }

    public function test_create_rejects_a_duplicate_plate_as_a_plate_field_error(): void
    {
        $userId = $this->user();
        $this->vehicle($this->user(), ['plate' => 'AB-24-CD']);

        $this->postJson('/vehicles', $this->payload(['plate' => 'AB-24-CD']), $this->headers($userId))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'plate');

        $this->assertDatabaseCount('vehicles', 1);
    }

    // ---------- GET /vehicles ----------

    public function test_list_returns_only_the_vehicles_of_the_user(): void
    {
        $userId = $this->user();
        $mine = $this->vehicle($userId, ['plate' => 'AA-11-AA']);
        $this->vehicle($this->user(), ['plate' => 'BB-22-BB']);

        $this->getJson('/vehicles', $this->headers($userId))
            ->assertOk()
            ->assertHeader('X-Total-Count', '1')
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);
    }

    public function test_list_is_empty_for_a_user_without_vehicles(): void
    {
        $this->getJson('/vehicles', $this->headers($this->user()))
            ->assertOk()
            ->assertHeader('X-Total-Count', '0')
            ->assertExactJson([]);
    }

    public function test_list_requires_the_user_header(): void
    {
        $this->getJson('/vehicles')
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'X-User-Id');
    }

    public function test_list_filters_by_type(): void
    {
        $userId = $this->user();
        $this->vehicle($userId, ['type' => 'CARRO', 'plate' => 'AA-11-AA']);
        $moto = $this->vehicle($userId, ['type' => 'MOTO', 'plate' => 'BB-22-BB']);

        $this->getJson('/vehicles?type=MOTO', $this->headers($userId))
            ->assertOk()
            ->assertHeader('X-Total-Count', '1')
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $moto->id);
    }

    public function test_list_paginates_and_reports_the_total_without_pagination(): void
    {
        $userId = $this->user();
        foreach (['AA-11-AA', 'BB-22-BB', 'CC-33-CC'] as $i => $plate) {
            $this->vehicle($userId, ['plate' => $plate, 'created_at' => now()->addMinutes($i)]);
        }

        $page = $this->getJson('/vehicles?limit=2&offset=0', $this->headers($userId))
            ->assertOk()
            ->assertHeader('X-Total-Count', '3')
            ->assertJsonCount(2);
        $this->assertSame(['AA-11-AA', 'BB-22-BB'], array_column($page->json(), 'plate'));

        $rest = $this->getJson('/vehicles?limit=2&offset=2', $this->headers($userId))
            ->assertOk()
            ->assertHeader('X-Total-Count', '3')
            ->assertJsonCount(1);
        $this->assertSame(['CC-33-CC'], array_column($rest->json(), 'plate'));
    }

    public function test_list_rejects_invalid_query_parameters(): void
    {
        $userId = $this->user();

        foreach (['type=BICICLETA', 'limit=0', 'limit=101', 'limit=abc', 'offset=-1'] as $query) {
            $this->getJson("/vehicles?{$query}", $this->headers($userId))
                ->assertStatus(400)
                ->assertHeader('Content-Type', 'application/problem+json');
        }
    }

    public function test_list_includes_next_service_and_open_alerts(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId, ['km' => 68420]);
        $this->part($vehicle, 'Pneus', 10000, 60000);               // próximo: 70000 => faltam 1580
        $oil = $this->part($vehicle, 'Óleo e filtro de óleo', 10000, 65000); // próximo: 75000 => faltam 6580
        $this->part($vehicle, 'Filtro de ar', 20000, 60000);        // próximo: 80000 => faltam 11580
        $this->part($vehicle, 'Inspeção visual', null, 0, 12);      // só meses: não conta para nextService

        $pneus = $vehicle->parts()->where('name', 'Pneus')->first();
        Alert::create([
            'vehicle_id' => $vehicle->id, 'type' => 'MANUTENCAO', 'trigger' => 'KM', 'status' => 'ABERTO',
            'title' => 'Pneus', 'part_id' => $pneus->id, 'target_km' => 70000,
        ]);
        Alert::create([
            'vehicle_id' => $vehicle->id, 'type' => 'MANUTENCAO', 'trigger' => 'KM', 'status' => 'FECHADO',
            'title' => 'Óleo', 'part_id' => $oil->id, 'target_km' => 60000, 'closed_at' => now(),
        ]);

        $this->getJson('/vehicles', $this->headers($userId))
            ->assertOk()
            ->assertJsonPath('0.openAlerts', 1)
            ->assertJsonPath('0.nextService.partId', $pneus->id)
            ->assertJsonPath('0.nextService.partName', 'Pneus')
            ->assertJsonPath('0.nextService.targetKm', 70000)
            ->assertJsonPath('0.nextService.kmRemaining', 1580)
            ->assertJsonPath('0.nextService.dueDate', null);
    }

    public function test_next_service_km_remaining_can_be_negative(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId, ['km' => 80000]);
        $this->part($vehicle, 'Pneus', 10000, 60000); // próximo: 70000 => ultrapassado em 10000

        $this->getJson("/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('nextService.kmRemaining', -10000);
    }

    public function test_next_service_has_a_due_date_when_the_part_has_a_months_interval(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId, ['km' => 1000]);
        $this->part($vehicle, 'Óleo e filtro de óleo', 10000, 0, 12, '2026-01-15');

        $this->getJson("/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('nextService.dueDate', '2027-01-15');
    }

    public function test_next_service_is_null_without_parts_with_a_km_interval(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);
        $this->part($vehicle, 'Inspeção visual', null, 0, 12);

        $this->getJson("/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('nextService', null);
        $this->getJson('/vehicles', $this->headers($userId))
            ->assertJsonPath('0.nextService', null);
    }

    // ---------- GET /vehicles/{vehicleId} ----------

    public function test_show_returns_the_vehicle(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);

        $this->getJson("/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('id', $vehicle->id)
            ->assertJsonPath('userId', $userId)
            ->assertJsonPath('openAlerts', 0);
    }

    public function test_show_with_the_owner_header_works(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);

        $this->getJson("/vehicles/{$vehicle->id}", $this->headers($userId))->assertOk();
    }

    public function test_show_of_another_users_vehicle_is_404_when_the_header_is_sent(): void
    {
        $vehicle = $this->vehicle($this->user());

        $this->getJson("/vehicles/{$vehicle->id}", $this->headers($this->user()))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_show_unknown_or_malformed_id_is_404(): void
    {
        $this->getJson('/vehicles/'.Str::uuid())->assertNotFound()->assertHeader('Content-Type', 'application/problem+json');
        $this->getJson('/vehicles/not-a-uuid')->assertNotFound();
    }

    // ---------- PATCH /vehicles/{vehicleId} ----------

    public function test_patch_updates_the_descriptive_fields(): void
    {
        $userId = $this->user();
        $vehicle = $this->vehicle($userId);

        $this->patchJson("/vehicles/{$vehicle->id}", [
            'brand' => 'Audi',
            'model' => 'A3',
            'firstRegistration' => '2019-02',
            'plate' => 'ZZ-99-ZZ',
        ], $this->headers($userId))
            ->assertOk()
            ->assertJsonPath('brand', 'Audi')
            ->assertJsonPath('model', 'A3')
            ->assertJsonPath('firstRegistration', '2019-02')
            ->assertJsonPath('plate', 'ZZ-99-ZZ')
            ->assertJsonPath('km', 68420);

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'brand' => 'Audi', 'plate' => 'ZZ-99-ZZ']);
    }

    public function test_patch_changes_only_the_fields_sent(): void
    {
        $vehicle = $this->vehicle($this->user());

        $this->patchJson("/vehicles/{$vehicle->id}", ['model' => 'Polo'])
            ->assertOk()
            ->assertJsonPath('model', 'Polo')
            ->assertJsonPath('brand', 'Volkswagen')
            ->assertJsonPath('plate', 'AB-24-CD');
    }

    public function test_patch_does_not_change_km_or_type(): void
    {
        $vehicle = $this->vehicle($this->user(), ['km' => 68420, 'type' => 'CARRO']);

        $this->patchJson("/vehicles/{$vehicle->id}", ['model' => 'Polo', 'km' => 1, 'type' => 'MOTO'])
            ->assertOk()
            ->assertJsonPath('km', 68420)
            ->assertJsonPath('type', 'CARRO');

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'km' => 68420, 'type' => 'CARRO']);
    }

    public function test_patch_with_the_same_plate_is_allowed(): void
    {
        $vehicle = $this->vehicle($this->user(), ['plate' => 'AB-24-CD']);

        $this->patchJson("/vehicles/{$vehicle->id}", ['plate' => 'AB-24-CD', 'brand' => 'Seat'])
            ->assertOk()
            ->assertJsonPath('plate', 'AB-24-CD');
    }

    public function test_patch_rejects_a_plate_used_by_another_vehicle(): void
    {
        $this->vehicle($this->user(), ['plate' => 'AA-11-AA']);
        $vehicle = $this->vehicle($this->user(), ['plate' => 'BB-22-BB']);

        $this->patchJson("/vehicles/{$vehicle->id}", ['plate' => 'AA-11-AA'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.field', 'plate');

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'plate' => 'BB-22-BB']);
    }

    public function test_patch_requires_at_least_one_field(): void
    {
        $vehicle = $this->vehicle($this->user());

        $this->patchJson("/vehicles/{$vehicle->id}", [])
            ->assertStatus(400)
            ->assertHeader('Content-Type', 'application/problem+json');
        // km e tipo não são campos editáveis: sozinhos também não contam.
        $this->patchJson("/vehicles/{$vehicle->id}", ['km' => 1])->assertStatus(400);
    }

    public function test_patch_rejects_invalid_fields(): void
    {
        $vehicle = $this->vehicle($this->user());

        $response = $this->patchJson("/vehicles/{$vehicle->id}", [
            'brand' => '',
            'firstRegistration' => '2021-6',
            'plate' => 'AB24CD',
        ])->assertStatus(400);

        $fields = array_unique(array_column($response->json('errors'), 'field'));
        sort($fields);
        $this->assertSame(['brand', 'firstRegistration', 'plate'], $fields);
    }

    public function test_patch_of_another_users_vehicle_is_404_and_changes_nothing(): void
    {
        $vehicle = $this->vehicle($this->user());

        $this->patchJson("/vehicles/{$vehicle->id}", ['model' => 'Polo'], $this->headers($this->user()))->assertNotFound();

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'model' => 'Golf']);
    }

    public function test_patch_of_an_unknown_vehicle_is_404(): void
    {
        $this->patchJson('/vehicles/'.Str::uuid(), ['model' => 'Polo'])->assertNotFound();
    }

    // ---------- DELETE /vehicles/{vehicleId} ----------

    public function test_delete_returns_204_and_removes_everything_that_belongs_to_the_vehicle(): void
    {
        $userId = $this->user();
        $id = $this->postJson('/vehicles', $this->payload(), $this->headers($userId))->json('id');
        $this->assertDatabaseCount('parts', 7);

        $this->deleteJson("/vehicles/{$id}", [], $this->headers($userId))
            ->assertNoContent();

        $this->assertDatabaseMissing('vehicles', ['id' => $id]);
        $this->assertDatabaseCount('parts', 0);
        $this->getJson("/vehicles/{$id}")->assertNotFound();
    }

    public function test_delete_of_another_users_vehicle_is_404_and_keeps_it(): void
    {
        $vehicle = $this->vehicle($this->user());

        $this->deleteJson("/vehicles/{$vehicle->id}", [], $this->headers($this->user()))->assertNotFound();

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
    }

    public function test_delete_of_an_unknown_vehicle_is_404(): void
    {
        $this->deleteJson('/vehicles/'.Str::uuid())->assertNotFound()->assertHeader('Content-Type', 'application/problem+json');
    }
}
