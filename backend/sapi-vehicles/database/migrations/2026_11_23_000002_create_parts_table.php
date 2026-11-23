<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// parts: plano de manutenção de cada veículo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete(); // R10
            $table->string('name', 60);
            $table->integer('interval_km')->nullable();     // nulo = sem limite por km
            $table->integer('interval_months')->nullable(); // nulo = sem limite por tempo
            $table->enum('interval_source', ['CATALOGO', 'UTILIZADOR'])->default('CATALOGO');
            $table->integer('last_service_km');
            $table->date('last_service_date');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['vehicle_id', 'name']);
            // Alvo das FKs compostas de maintenance_parts e alerts: a peça tem de ser do mesmo veículo
            $table->unique(['id', 'vehicle_id']);
        });

        DB::statement('ALTER TABLE parts ADD CONSTRAINT parts_interval_km_min CHECK (interval_km >= 1)');
        DB::statement('ALTER TABLE parts ADD CONSTRAINT parts_interval_months_min CHECK (interval_months >= 1)');
        DB::statement('ALTER TABLE parts ADD CONSTRAINT parts_last_service_km_not_negative CHECK (last_service_km >= 0)');
        // Pelo menos um limite ativo
        DB::statement('ALTER TABLE parts ADD CONSTRAINT parts_one_interval_active
            CHECK (interval_km IS NOT NULL OR interval_months IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
