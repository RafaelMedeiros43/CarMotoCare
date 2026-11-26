<?php

namespace App\Models;

use Carbon\CarbonInterface;
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

    /** Km of the next service (docs/data/data-model.md, section 4). Null without a km interval. */
    public function nextKm(): ?int
    {
        return $this->interval_km === null ? null : $this->last_service_km + $this->interval_km;
    }

    /**
     * Km left until the next service, negative when overdue. Null without a km interval.
     * The km of the vehicle is a parameter so this never runs a query per part.
     */
    public function kmRemaining(int $vehicleKm): ?int
    {
        $nextKm = $this->nextKm();

        return $nextKm === null ? null : $nextKm - $vehicleKm;
    }

    /** Date of the next service (31 Jan + 1 month = 28 Feb). Null without a months interval. */
    public function nextDate(): ?CarbonInterface
    {
        return $this->interval_months === null
            ? null
            : $this->last_service_date->copy()->addMonthsNoOverflow($this->interval_months);
    }

    /** Days from today until the next date, negative when overdue. Null without a months interval. */
    public function daysRemaining(): ?int
    {
        $nextDate = $this->nextDate();

        return $nextDate === null ? null : (int) today()->diffInDays($nextDate, false);
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
