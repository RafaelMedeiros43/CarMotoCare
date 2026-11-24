<?php

namespace App\Services;

use App\Exceptions\ProblemException;
use App\Models\Alert;
use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance history. R7: the cost is the total (the cost per part is informative). R8: registering a
 * maintenance with parts updates their last service and closes their open alerts.
 */
class MaintenanceService
{
    /**
     * History of a vehicle, most recent first.
     *
     * @param  ?string  $userId  if given, the vehicle must belong to this user (otherwise 404)
     * @return Collection<int, Maintenance>
     */
    public function list(string $vehicleId, ?string $userId = null): Collection
    {
        return $this->findVehicle($vehicleId, $userId)
            ->maintenances()
            ->with('parts')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->get();
    }

    public function find(string $vehicleId, string $maintenanceId, ?string $userId = null): Maintenance
    {
        return $this->findVehicle($vehicleId, $userId)
            ->maintenances()
            ->with('parts')
            ->findOrFail($maintenanceId);
    }

    /**
     * Registers the maintenance, updates the parts and closes their alerts, all in one transaction.
     *
     * A part is only updated when the maintenance is not older than its last service, so entering an old
     * maintenance does not move a part back in time (the maintenance is recorded either way).
     * The km of the vehicle is never changed (R8).
     *
     * @param  array<string, mixed>  $data  validated body (camelCase)
     * @return array{0: Maintenance, 1: array<int, string>}  the maintenance and the ids of the closed alerts
     */
    public function create(string $vehicleId, ?string $userId, array $data): array
    {
        $vehicle = $this->findVehicle($vehicleId, $userId);
        $inputs = $data['parts'] ?? [];
        $parts = $this->partsOf($vehicle, $inputs);

        return DB::transaction(function () use ($vehicle, $data, $inputs, $parts) {
            $km = $data['km'] ?? $vehicle->km;

            $maintenance = $vehicle->maintenances()->create([
                'type' => $data['type'],
                'date' => $data['date'],
                'km' => $km,
                'cost' => $data['cost'],
                'notes' => $data['notes'] ?? null,
            ]);

            $updatedPartIds = [];
            foreach ($inputs as $input) {
                $part = $parts[$input['partId']];

                $maintenance->parts()->attach($part->id, ['vehicle_id' => $vehicle->id, 'cost' => $input['cost'] ?? null]);

                if ($data['date'] >= $part->last_service_date->toDateString()) {
                    $part->update(['last_service_km' => $km, 'last_service_date' => $data['date']]);
                    $updatedPartIds[] = $part->id;
                }
            }

            return [$maintenance->refresh()->load('parts'), $this->closeAlertsOf($vehicle, $updatedPartIds)];
        });
    }

    /**
     * Corrects cost and notes only.
     *
     * @param  array{cost?: float|int|string, notes?: ?string}  $data
     */
    public function update(string $vehicleId, string $maintenanceId, ?string $userId, array $data): Maintenance
    {
        $maintenance = $this->findVehicle($vehicleId, $userId)->maintenances()->findOrFail($maintenanceId);

        $maintenance->update(array_intersect_key($data, array_flip(['cost', 'notes'])));

        return $maintenance->refresh()->load('parts');
    }

    /**
     * The parts of the request, indexed by id. A part that is not a part of this vehicle is a 400 on its field.
     *
     * @param  array<int, array{partId: string}>  $inputs
     * @return array<string, \App\Models\Part>
     */
    private function partsOf(Vehicle $vehicle, array $inputs): array
    {
        if ($inputs === []) {
            return [];
        }

        $found = $vehicle->parts()->whereIn('id', array_column($inputs, 'partId'))->get()->keyBy('id');

        $errors = [];
        foreach ($inputs as $index => $input) {
            if (! $found->has($input['partId'])) {
                $errors[] = ['field' => "parts.{$index}.partId", 'message' => 'A peça não existe neste veículo.'];
            }
        }
        if ($errors !== []) {
            throw new ProblemException(400, 'Pedido inválido', 'Há campos inválidos.', $errors);
        }

        return $found->all();
    }

    /**
     * Closes the open alert of each part (R1: at most one per part).
     *
     * @param  array<int, string>  $partIds
     * @return array<int, string>  ids of the alerts closed
     */
    private function closeAlertsOf(Vehicle $vehicle, array $partIds): array
    {
        if ($partIds === []) {
            return [];
        }

        $ids = Alert::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('part_id', $partIds)
            ->where('status', 'ABERTO')
            ->pluck('id')
            ->all();

        if ($ids !== []) {
            Alert::query()->whereIn('id', $ids)->update(['status' => 'FECHADO', 'closed_at' => now()]);
        }

        return $ids;
    }

    private function findVehicle(string $vehicleId, ?string $userId): Vehicle
    {
        return Vehicle::query()->ownedBy($userId)->findOrFail($vehicleId);
    }
}
