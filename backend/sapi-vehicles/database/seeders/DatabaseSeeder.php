<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Dados de desenvolvimento. Não usar em produção.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * UUID fixo do utilizador de demonstração (demo@carmotocare.pt). Tem de ser igual ao de
     * sapi-users (DatabaseSeeder::DEMO_USER_ID); ver docs/data/data-model.md.
     */
    public const DEMO_USER_ID = '00000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        $vehicles = [
            ['type' => 'CARRO', 'brand' => 'Volkswagen', 'model' => 'Golf', 'first_registration' => '2021-06', 'plate' => 'AB-24-CD', 'km' => 68420],
            ['type' => 'MOTO', 'brand' => 'Honda', 'model' => 'CB500F', 'first_registration' => '2022-03', 'plate' => '45-XY-12', 'km' => 15300],
        ];

        $today = now()->toDateString();

        foreach ($vehicles as $data) {
            $vehicle = Vehicle::create(['user_id' => self::DEMO_USER_ID] + $data);

            // Plano de peças por defeito, com o último serviço = km e data de criação do veículo
            foreach (config("default_parts.{$vehicle->type}") as $part) {
                $vehicle->parts()->create($part + [
                    'last_service_km' => $vehicle->km,
                    'last_service_date' => $today,
                ]);
            }

            // Um prazo pendente de cada tipo
            $vehicle->deadlines()->createMany([
                ['type' => 'IUC', 'due_date' => now()->addMonths(2)->toDateString()],
                ['type' => 'SEGURO', 'due_date' => now()->addMonths(5)->toDateString()],
                ['type' => 'INSPECAO', 'due_date' => now()->addMonths(8)->toDateString()],
            ]);
        }
    }
}
