<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * inspection_deficiencies. Sem created_at/updated_at.
 */
class InspectionDeficiency extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'inspection_id',
        'description',
        'severity',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}
