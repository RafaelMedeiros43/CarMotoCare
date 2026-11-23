<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// alerts: origem é uma peça (MANUTENCAO) ou um prazo (IUC, SEGURO, INSPECAO).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete(); // R10
            $table->enum('type', ['IUC', 'SEGURO', 'INSPECAO', 'MANUTENCAO']);
            $table->enum('trigger', ['KM', 'DATA']);
            $table->enum('status', ['ABERTO', 'FECHADO'])->default('ABERTO');
            $table->string('title', 100);
            $table->uuid('part_id')->nullable();
            $table->uuid('deadline_id')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('target_km')->nullable(); // kmRemaining = target_km - vehicles.km, calcula-se (D6)
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('closed_at')->nullable();

            $table->index(['vehicle_id', 'status']);

            // FKs compostas (MATCH SIMPLE: só são verificadas quando part_id / deadline_id não são nulos).
            // A peça tem de ser do mesmo veículo do alerta.
            $table->foreign(['part_id', 'vehicle_id'])
                ->references(['id', 'vehicle_id'])->on('parts')
                ->cascadeOnDelete();
            // O prazo tem de ser do mesmo veículo e do mesmo tipo (um alerta SEGURO não aponta para um prazo IUC).
            $table->foreign(['deadline_id', 'vehicle_id', 'type'])
                ->references(['id', 'vehicle_id', 'type'])->on('deadlines')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE alerts ADD CONSTRAINT alerts_target_km_not_negative CHECK (target_km >= 0)');
        // R4: exatamente uma origem
        DB::statement('ALTER TABLE alerts ADD CONSTRAINT alerts_one_origin
            CHECK (num_nonnulls(part_id, deadline_id) = 1)');
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT alerts_part_iff_maintenance
            CHECK ((type = 'MANUTENCAO') = (part_id IS NOT NULL))");
        // Prazos só disparam por data; o km só faz sentido em peças
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT alerts_deadline_trigger_is_date
            CHECK (type = 'MANUTENCAO' OR trigger = 'DATA')");
        // Cada gatilho tem o seu valor de referência
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT alerts_km_trigger_has_target
            CHECK (trigger <> 'KM' OR target_km IS NOT NULL)");
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT alerts_date_trigger_has_due_date
            CHECK (trigger <> 'DATA' OR due_date IS NOT NULL)");
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT alerts_closed_has_date
            CHECK ((status = 'FECHADO') = (closed_at IS NOT NULL))");
        // R1: no máximo um alerta aberto por peça (e por prazo); dá o 409 do POST /alerts
        DB::statement("CREATE UNIQUE INDEX alerts_one_open_part ON alerts (part_id) WHERE status = 'ABERTO'");
        DB::statement("CREATE UNIQUE INDEX alerts_one_open_deadline ON alerts (deadline_id) WHERE status = 'ABERTO'");
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
