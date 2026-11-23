<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * obd_devices. A chave primária é a etiqueta do dispositivo (device_id, texto), não um UUID,
 * por isso não usa HasUuids. Um dispositivo por veículo (1:1).
 */
class ObdDevice extends Model
{
    protected $primaryKey = 'device_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false; // tem paired_at e last_reading_at em vez de created_at/updated_at

    protected $fillable = [
        'device_id',
        'vehicle_id',
        'name',
        'paired_at',
        'last_reading_at',
    ];

    protected function casts(): array
    {
        return [
            'paired_at' => 'datetime',
            'last_reading_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
