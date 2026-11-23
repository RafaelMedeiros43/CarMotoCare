<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// maintenances e maintenance_parts (peças trocadas em cada manutenção).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete(); // R10
            $table->enum('type', [
                'REVISAO_PERIODICA', 'MUDANCA_OLEO', 'SUBSTITUICAO_PNEUS', 'REPARACAO', 'AVARIA', 'OUTRO',
            ]);
            $table->date('date');
            $table->integer('km');
            $table->decimal('cost', 10, 2); // custo total (R7)
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // Alvo da FK composta de maintenance_parts
            $table->unique(['id', 'vehicle_id']);
        });

        DB::statement('ALTER TABLE maintenances ADD CONSTRAINT maintenances_km_not_negative CHECK (km >= 0)');
        DB::statement('ALTER TABLE maintenances ADD CONSTRAINT maintenances_cost_not_negative CHECK (cost >= 0)');

        Schema::create('maintenance_parts', function (Blueprint $table) {
            $table->uuid('maintenance_id');
            $table->uuid('part_id');
            // Redundante de propósito: com as duas FKs compostas garante que a manutenção e a peça são do mesmo veículo
            $table->uuid('vehicle_id');
            $table->decimal('cost', 10, 2)->nullable(); // informativo (R7)

            $table->primary(['maintenance_id', 'part_id']);
            $table->index('part_id');

            $table->foreign(['maintenance_id', 'vehicle_id'])
                ->references(['id', 'vehicle_id'])->on('maintenances')
                ->cascadeOnDelete();
            // NO ACTION (e não CASCADE): apagar uma peça não pode apagar o histórico de manutenções.
            // DEFERRABLE INITIALLY DEFERRED: a verificação só acontece no fim da transação. Sem isto, apagar o
            // veículo falhava, porque o PostgreSQL apaga as peças (cascata) antes de as linhas desta tabela
            // terem sido apagadas pela cascata das manutenções.
            $table->foreign(['part_id', 'vehicle_id'])
                ->references(['id', 'vehicle_id'])->on('parts')
                ->noActionOnDelete()
                ->deferrable()
                ->initiallyImmediate(false);
        });

        DB::statement('ALTER TABLE maintenance_parts ADD CONSTRAINT maintenance_parts_cost_not_negative
            CHECK (cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_parts');
        Schema::dropIfExists('maintenances');
    }
};
