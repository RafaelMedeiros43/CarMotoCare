<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * maintenances. cost é o custo total (R7); o custo por peça está em maintenance_parts.
 */
class Maintenance extends Model
{
    use HasUuids;

    protected $fillable = [
        'vehicle_id',
        'type',
        'date',
        'km',
        'cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'km' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** No attach, passar sempre vehicle_id na tabela pivot (ver MaintenancePart). */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'maintenance_parts')
            ->using(MaintenancePart::class)
            ->withPivot('cost');
    }
}
