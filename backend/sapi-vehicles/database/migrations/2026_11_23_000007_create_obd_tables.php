<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// obd_devices (1:1 com o veículo) e obd_readings.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obd_devices', function (Blueprint $table) {
            $table->string('device_id', 50)->primary(); // etiqueta do dispositivo (= utilizador MQTT)
            $table->foreignUuid('vehicle_id')->unique()->constrained('vehicles')->cascadeOnDelete(); // R10
            $table->string('name', 60)->nullable();
            $table->timestampTz('paired_at')->useCurrent();
            $table->timestampTz('last_reading_at')->nullable();
        });

        Schema::create('obd_readings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete(); // R10
            $table->string('device_id', 50); // sem FK: a leitura fica mesmo que o dispositivo seja trocado
            $table->timestampTz('read_at');
            $table->integer('km')->nullable();
            $table->decimal('oil_temp', 5, 1)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE obd_readings ADD CONSTRAINT obd_readings_km_not_negative CHECK (km >= 0)');
        DB::statement('ALTER TABLE obd_readings ADD CONSTRAINT obd_readings_latitude_range
            CHECK (latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE obd_readings ADD CONSTRAINT obd_readings_longitude_range
            CHECK (longitude BETWEEN -180 AND 180)');
        DB::statement('ALTER TABLE obd_readings ADD CONSTRAINT obd_readings_gps_both_or_none
            CHECK ((latitude IS NULL) = (longitude IS NULL))');
        // GET /readings/latest
        DB::statement('CREATE INDEX obd_readings_vehicle_id_read_at_index ON obd_readings (vehicle_id, read_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('obd_readings');
        Schema::dropIfExists('obd_devices');
    }
};
