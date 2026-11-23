<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * expenses: VIEW sobre manutenções, inspeções e prazos pagos (D5). Só de leitura.
 * O id é o da linha de origem (manutenção, inspeção ou prazo).
 */
class Expense extends Model
{
    protected $table = 'expenses';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'km' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('Expense é uma view só de leitura: altere a manutenção, inspeção ou prazo.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Expense é uma view só de leitura: altere a manutenção, inspeção ou prazo.');
    }
}
