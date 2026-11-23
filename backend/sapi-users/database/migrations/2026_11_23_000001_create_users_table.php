<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// users (docs/data/data-model.md, secção 3). Schema public, partilhado com sapi-vehicles (D3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 100);
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255); // bcrypt (Hash::make)
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_name_not_empty CHECK (length(name) > 0)');
    }

    public function down(): void
    {
        // vehicles.user_id tem FK para users: o rollback de sapi-vehicles tem de ser feito primeiro.
        if (Schema::hasTable('vehicles')) {
            throw new RuntimeException(
                'Não é possível apagar users enquanto vehicles existir (FK vehicles.user_id). '
                .'Faz primeiro "php artisan migrate:rollback" em sapi-vehicles.'
            );
        }

        Schema::dropIfExists('users');
    }
};
