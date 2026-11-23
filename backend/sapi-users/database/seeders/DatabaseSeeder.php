<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de desenvolvimento. Não usar em produção.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * UUID fixo do utilizador de demonstração. Tem de ser igual ao de
     * sapi-vehicles (DatabaseSeeder::DEMO_USER_ID); ver docs/data/data-model.md.
     */
    public const DEMO_USER_ID = '00000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        User::create([
            'id' => self::DEMO_USER_ID,
            'name' => 'Utilizador Demo',
            'email' => 'demo@carmotocare.pt',
            'password_hash' => 'demo1234', // o cast "hashed" do modelo aplica bcrypt
        ]);
    }
}
