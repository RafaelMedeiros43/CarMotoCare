<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// vehicles (docs/data/data-model.md, secção 3). Schema public; users é criada por sapi-users, que migra primeiro (D3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->index()->constrained('users')->cascadeOnDelete(); // FK para users (D3)
            $table->enum('type', ['CARRO', 'MOTO']);
            $table->string('brand', 50);
            $table->string('model', 50);
            $table->char('first_registration', 7); // AAAA-MM
            $table->string('plate', 8)->unique();
            $table->integer('km'); // R3 (nunca desce) é garantida na SAPI: 409 no PUT /mileage
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_first_registration_format
            CHECK (first_registration ~ '^\\d{4}-(0[1-9]|1[0-2])$')");
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_plate_format
            CHECK (plate ~ '^[A-Z0-9]{2}-[A-Z0-9]{2}-[A-Z0-9]{2}$')");
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_km_not_negative CHECK (km >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
