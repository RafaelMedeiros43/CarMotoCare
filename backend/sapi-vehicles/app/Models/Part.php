<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * parts. nextKm, kmRemaining, nextDate e daysRemaining calculam-se (D6).
 */
class Part extends Model
{
    use HasUuids;

    protected $fillable = [
        'vehicle_id',
        'name',
        'interval_km',
        'interval_months',
        'interval_source',
        'last_service_km',
        'last_service_date',
    ];

    protected function casts(): array
    {
        return [
            'interval_km' => 'integer',
            'interval_months' => 'integer',
            'last_service_km' => 'integer',
            'last_service_date' => 'date:Y-m-d',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenances(): BelongsToMany
    {
        return $this->belongsToMany(Maintenance::class, 'maintenance_parts')
            ->using(MaintenancePart::class)
            ->withPivot('cost');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
