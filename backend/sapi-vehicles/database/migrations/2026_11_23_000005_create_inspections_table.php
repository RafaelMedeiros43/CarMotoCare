<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// inspections e inspection_deficiencies.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete(); // R10
            $table->date('date');
            $table->enum('result', ['APROVADA', 'REPROVADA']);
            $table->decimal('amount', 10, 2);
            $table->integer('km'); // copiada do veículo no registo
            $table->date('valid_until')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement('ALTER TABLE inspections ADD CONSTRAINT inspections_amount_not_negative CHECK (amount >= 0)');
        DB::statement('ALTER TABLE inspections ADD CONSTRAINT inspections_km_not_negative CHECK (km >= 0)');
        // R6: validade só se aprovada (e obrigatória se aprovada)
        DB::statement("ALTER TABLE inspections ADD CONSTRAINT inspections_valid_until_only_if_approved
            CHECK ((result = 'APROVADA') = (valid_until IS NOT NULL))");

        Schema::create('inspection_deficiencies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->string('description', 255);
            $table->enum('severity', ['LIGEIRA', 'GRAVE']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_deficiencies');
        Schema::dropIfExists('inspections');
    }
};
