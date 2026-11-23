<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * obd_readings. device_id não tem FK: a leitura fica mesmo que o dispositivo seja trocado.
 * Tem created_at, mas não updated_at.
 */
class ObdReading extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'vehicle_id',
        'device_id',
        'read_at',
        'km',
        'oil_temp',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'km' => 'integer',
            'oil_temp' => 'decimal:1',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
