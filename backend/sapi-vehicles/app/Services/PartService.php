<?php

namespace App\Services;

use App\Exceptions\ProblemException;
use App\Models\Part;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;

/**
 * Parts of the maintenance plan of a vehicle.
 */
class PartService
{
    /**
     * Parts in the order of the default plan (the order they were created in: the ids are time ordered).
     *
     * @param  ?string  $userId  if given, the vehicle must belong to this user (otherwise 404)
     * @return Collection<int, Part>
     */
    public function listForVehicle(string $vehicleId, ?string $userId = null): Collection
    {
        $vehicle = Vehicle::query()->ownedBy($userId)->findOrFail($vehicleId);

        $parts = $vehicle->parts()->orderBy('created_at')->orderBy('id')->get();

        // PartResource needs the km of the vehicle: hand it over to avoid one query per part.
        return $parts->each->setRelation('vehicle', $vehicle);
    }

    /**
     * Changes the interval and/or the reference (last service) of a part. No maintenance is created and no alert
     * is closed (R9). The source becomes UTILIZADOR only when an interval ends up different from the current one.
     * A field missing from the data is not changed.
     *
     * @param  ?string  $userId  if given, the vehicle must belong to this user (otherwise 404)
     * @param  array{intervalKm?: ?int, intervalMonths?: ?int, lastServiceDate?: string, lastServiceKm?: int}  $data
     */
    public function update(string $vehicleId, string $partId, ?string $userId, array $data): Part
    {
        $vehicle = Vehicle::query()->ownedBy($userId)->findOrFail($vehicleId);
        $part = $vehicle->parts()->findOrFail($partId);

        $columns = [
            'intervalKm' => 'interval_km',
            'intervalMonths' => 'interval_months',
            'lastServiceDate' => 'last_service_date',
            'lastServiceKm' => 'last_service_km',
        ];
        foreach (array_intersect_key($columns, $data) as $field => $column) {
            $part->{$column} = $data[$field];
        }

        $errors = [];
        // Only a km that is being sent is checked: a stored one must not block a change of interval.
        if (isset($data['lastServiceKm']) && $data['lastServiceKm'] > $vehicle->km) {
            $errors[] = [
                'field' => 'lastServiceKm',
                'message' => "A quilometragem do último serviço não pode ser superior à atual do veículo ({$vehicle->km} km).",
            ];
        }
        if ($part->interval_km === null && $part->interval_months === null) {
            $errors[] = ['field' => 'body', 'message' => 'A peça tem de manter pelo menos um limite: em km ou em meses.'];
        }
        if ($errors !== []) {
            throw new ProblemException(400, 'Pedido inválido', 'Há campos inválidos.', $errors);
        }

        // isDirty compares after the casts, so "10000" over 10000 is not a change.
        if ($part->isDirty(['interval_km', 'interval_months'])) {
            $part->interval_source = 'UTILIZADOR';
        }

        $part->save();

        return $part->setRelation('vehicle', $vehicle);
    }
}
