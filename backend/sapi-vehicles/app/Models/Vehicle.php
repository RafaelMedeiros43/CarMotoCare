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
