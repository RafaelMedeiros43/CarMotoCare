<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;

/**
 * Total expenses of a vehicle: the expenses view (D5) over maintenances, inspections and paid IUC and seguro.
 */
class ExpenseService
{
    /**
     * Most recent first. No filters: the period is filtered in the browser.
     *
     * @param  ?string  $userId  if given, the vehicle must belong to this user (otherwise 404)
     * @return Collection<int, Expense>
     */
    public function listForVehicle(string $vehicleId, ?string $userId = null): Collection
    {
        $vehicle = Vehicle::query()->ownedBy($userId)->findOrFail($vehicleId);

        return Expense::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('date')
            ->orderBy('id')
            ->get();
    }
}
