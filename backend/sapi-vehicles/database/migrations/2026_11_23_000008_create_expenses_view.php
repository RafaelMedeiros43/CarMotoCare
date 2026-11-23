<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// D5: despesas não têm tabela. VIEW sobre manutenções, inspeções e prazos pagos (R7).
// GET /vehicles/{id}/expenses lê esta view.
// Atenção: enquanto a view existir, o PostgreSQL não deixa alterar o tipo das colunas que ela usa
// (maintenances.date/km/cost/notes, inspections.date/amount, deadlines.type/paid_at/amount/description/status).
// Uma migration futura que mude uma delas tem de fazer DROP VIEW expenses, alterar e voltar a criar a view.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW expenses AS
                SELECT id, vehicle_id, 'MANUTENCAO'::varchar AS category, date, cost AS amount, notes AS description, km
                  FROM maintenances
                UNION ALL
                SELECT id, vehicle_id, 'INSPECAO', date, amount, NULL, NULL
                  FROM inspections
                UNION ALL
                SELECT id, vehicle_id, type, paid_at, amount, description, NULL
                  FROM deadlines
                 WHERE status = 'CONCLUIDO' AND type IN ('IUC', 'SEGURO')
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS expenses');
    }
};
