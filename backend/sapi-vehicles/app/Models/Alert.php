<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * alerts. Origem é uma peça OU um prazo (R4). userId vem do veículo (junção, não se guarda).
 * Tem created_at e closed_at, mas não updated_at.
 */
class Alert extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'vehicle_id',
        'type',
        'trigger',
        'status',
        'title',
        'part_id',
        'deadline_id',
        'due_date',
        'target_km',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'target_km' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * kmRemaining = target_km - km atual do veículo. Não se guarda (D6), porque mudaria a cada PUT /mileage.
     * Usa a relação vehicle: carregar com with('vehicle') nas listagens para evitar N+1.
     */
    protected function kmRemaining(): Attribute
    {
        return Attribute::get(fn () => $this->target_km === null
            ? null
            : $this->target_km - $this->vehicle->km);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function deadline(): BelongsTo
    {
        return $this->belongsTo(Deadline::class);
    }
}
