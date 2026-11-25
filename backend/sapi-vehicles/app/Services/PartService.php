<?php

namespace App\Services;

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
}
