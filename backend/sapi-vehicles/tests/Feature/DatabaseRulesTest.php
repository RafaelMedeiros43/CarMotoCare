<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Exemplo de testes com base de dados. Confirma algumas regras que a própria base garante
 * (docs/data/data-model.md). Os testes dos endpoints seguem o mesmo padrão, com $this->postJson(...).
 *
 * RefreshDatabase: na primeira vez corre as migrações na base carmotocare_test_vehicles; depois cada teste
 * corre dentro de uma transação que é desfeita no fim, por isso os testes não interferem uns com os outros.
 */
class DatabaseRulesTest extends TestCase
{
    use RefreshDatabase;

    /** Cria um utilizador (tabela da sapi-users, só existe nos testes) e um veículo desse utilizador. */
    private function vehicle(string $plate = 'AB-24-CD', int $km = 68420): Vehicle
    {
        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Demo',
            'email' => "{$userId}@example.com",
            'password_hash' => 'x',
        ]);

        return Vehicle::create([
            'user_id' => $userId,
            'type' => 'CARRO',
            'brand' => 'Volkswagen',
            'model' => 'Golf',
            'first_registration' => '2021-06',
            'plate' => $plate,
            'km' => $km,
        ]);
    }

    private function part(Vehicle $vehicle, string $name = 'Pneus')
    {
        return $vehicle->parts()->create([
            'name' => $name,
            'interval_km' => 10000,
            'last_service_km' => 60000,
            'last_service_date' => '2026-01-01',
        ]);
    }

    public function test_plate_must_follow_portuguese_format(): void
    {
        $this->expectException(QueryException::class);

        $this->vehicle(plate: 'AB24CD');
    }

    public function test_alert_km_remaining_is_calculated_from_vehicle_km(): void
    {
        $vehicle = $this->vehicle(km: 68420);
        $alert = $vehicle->alerts()->create([
            'type' => 'MANUTENCAO',
            'trigger' => 'KM',
            'title' => 'Pneus',
            'part_id' => $this->part($vehicle)->id,
            'target_km' => 70000,
        ]);

        $this->assertSame(1580, $alert->km_remaining);

        $vehicle->update(['km' => 69000]);
        $this->assertSame(1000, $alert->fresh()->km_remaining);
    }

    public function test_alert_cannot_use_part_of_another_vehicle(): void
    {
        $golf = $this->vehicle('AB-24-CD');
        $moto = $this->vehicle('45-XY-12');

        $this->expectException(QueryException::class);

        $golf->alerts()->create([
            'type' => 'MANUTENCAO',
            'trigger' => 'KM',
            'title' => 'Pneus',
            'part_id' => $this->part($moto)->id,
            'target_km' => 70000,
        ]);
    }

    public function test_deleting_vehicle_deletes_everything_that_belongs_to_it(): void
    {
        $vehicle = $this->vehicle();
        $part = $this->part($vehicle);
        $maintenance = $vehicle->maintenances()->create([
            'type' => 'REPARACAO', 'date' => '2026-11-01', 'km' => 68000, 'cost' => 100,
        ]);
        $maintenance->parts()->attach($part->id, ['vehicle_id' => $vehicle->id, 'cost' => 25]);

        $vehicle->delete();

        // A FK maintenance_parts -> parts é DEFERRED (só é verificada no COMMIT). Como o RefreshDatabase
        // nunca faz commit, força-se a verificação agora; sem isto o teste passava mesmo com a cascata errada.
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        $this->assertDatabaseCount('parts', 0);
        $this->assertDatabaseCount('maintenances', 0);
        $this->assertDatabaseCount('maintenance_parts', 0);
    }
}
