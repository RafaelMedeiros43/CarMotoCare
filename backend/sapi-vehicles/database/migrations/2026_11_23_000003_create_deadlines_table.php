<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// deadlines: prazos legais (IUC, seguro, inspeção).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deadlines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete(); // R10
            $table->enum('type', ['IUC', 'SEGURO', 'INSPECAO']);
            $table->enum('status', ['PENDENTE', 'CONCLUIDO'])->default('PENDENTE');
            $table->date('due_date');
            $table->date('paid_at')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['status', 'due_date']);
            // Alvo da FK composta de alerts: o prazo tem de ser do mesmo veículo e do mesmo tipo do alerta
            $table->unique(['id', 'vehicle_id', 'type']);
        });

        DB::statement('ALTER TABLE deadlines ADD CONSTRAINT deadlines_amount_positive CHECK (amount > 0)');
        // Um prazo concluído tem data de pagamento
        DB::statement("ALTER TABLE deadlines ADD CONSTRAINT deadlines_paid_has_date
            CHECK (status = 'PENDENTE' OR paid_at IS NOT NULL)");
        // IUC e SEGURO pagos entram na view expenses, por isso têm valor (o da inspeção está em inspections)
        DB::statement("ALTER TABLE deadlines ADD CONSTRAINT deadlines_paid_has_amount
            CHECK (status = 'PENDENTE' OR type = 'INSPECAO' OR amount IS NOT NULL)");
        // R2: no máximo um prazo pendente por tipo e veículo
        DB::statement("CREATE UNIQUE INDEX deadlines_one_pending ON deadlines (vehicle_id, type)
            WHERE status = 'PENDENTE'");
    }

    public function down(): void
    {
        Schema::dropIfExists('deadlines');
    }
};
