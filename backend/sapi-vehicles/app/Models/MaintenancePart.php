<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * maintenance_parts: peças trocadas numa manutenção.
 * Chave primária composta (maintenance_id, part_id), por isso não usa HasUuids.
 * vehicle_id é obrigatório: as FKs compostas garantem que a manutenção e a peça são do mesmo veículo.
 * Ex.: $maintenance->parts()->attach($partId, ['vehicle_id' => $maintenance->vehicle_id, 'cost' => 25.0]);
 */
class MaintenancePart extends Pivot
{
    protected $table = 'maintenance_parts';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'maintenance_id',
        'part_id',
        'vehicle_id',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2', // informativo (R7)
        ];
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
