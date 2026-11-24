<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * vehicles. user_id é FK para users (garantida pela base de dados; sem modelo User nesta SAPI, D3).
 * nextService e openAlerts calculam-se nas consultas (D6).
 */
class Vehicle extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'brand',
        'model',
        'first_registration',
        'plate',
        'km',
    ];

    protected function casts(): array
    {
        return [
            'km' => 'integer',
        ];
    }

    /**
     * Part with the fewest km remaining (NextService in the contract; docs/data/data-model.md, section 4).
     * Only parts with a km interval count. Needs the "parts" relation loaded. Null if there is none.
     *
     * @return array{partId: string, partName: string, targetKm: int, kmRemaining: int, dueDate: ?string}|null
     */
    public function nextService(): ?array
    {
        $next = null;

        foreach ($this->parts as $part) {
            if ($part->interval_km === null) {
                continue;
            }

            $targetKm = $part->last_service_km + $part->interval_km;
            $kmRemaining = $targetKm - $this->km;

            if ($next === null || $kmRemaining < $next['kmRemaining']) {
                $next = [
                    'partId' => $part->id,
                    'partName' => $part->name,
                    'targetKm' => $targetKm,
                    'kmRemaining' => $kmRemaining,
                    'dueDate' => $part->interval_months === null
                        ? null
                        : $part->last_service_date->copy()->addMonthsNoOverflow($part->interval_months)->toDateString(),
                ];
            }
        }

        return $next;
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function obdDevice(): HasOne
    {
        return $this->hasOne(ObdDevice::class);
    }

    public function obdReadings(): HasMany
    {
        return $this->hasMany(ObdReading::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
