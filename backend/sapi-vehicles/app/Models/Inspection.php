<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * inspections. valid_until só existe se o resultado for APROVADA (R6).
 */
class Inspection extends Model
{
    use HasUuids;

    protected $fillable = [
        'vehicle_id',
        'date',
        'result',
        'amount',
        'km',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'km' => 'integer',
            'valid_until' => 'date:Y-m-d',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function deficiencies(): HasMany
    {
        return $this->hasMany(InspectionDeficiency::class);
    }
}
