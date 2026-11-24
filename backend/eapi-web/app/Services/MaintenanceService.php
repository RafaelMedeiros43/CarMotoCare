<?php

namespace App\Services;

use App\Clients\SapiVehiclesClient;
use Illuminate\Support\Arr;

/**
 * Maintenance history and expenses of the logged-in user's vehicles. The data and the rules (R7, R8) live in
 * the sapi-vehicles; this service only orchestrates. The user id always comes from the JWT.
 */
class MaintenanceService
{
    public function __construct(private readonly SapiVehiclesClient $vehicles) {}

    /** @return array<int, array<string, mixed>> */
    public function list(string $userId, string $vehicleId): array
    {
        return $this->vehicles->listMaintenances($userId, $vehicleId);
    }

    /** @return array<string, mixed> */
    public function find(string $userId, string $vehicleId, string $maintenanceId): array
    {
        return $this->vehicles->getMaintenance($userId, $vehicleId, $maintenanceId);
    }

    /**
     * Registers the maintenance. The sapi-vehicles updates the parts and closes their alerts in one transaction.
     * A null km is left out so that the SAPI uses the current km of the vehicle.
     *
     * @param  array<string, mixed>  $data  validated body
     * @return array<string, mixed>  the maintenance plus closedAlertIds
     */
    public function create(string $userId, string $vehicleId, array $data): array
    {
        $body = Arr::only($data, ['type', 'date', 'cost', 'notes']);

        if (isset($data['km'])) {
            $body['km'] = $data['km'];
        }

        if (! empty($data['parts'])) {
            $body['parts'] = array_map(fn (array $part) => Arr::only($part, ['partId', 'cost']), $data['parts']);
        }

        return $this->vehicles->createMaintenance($userId, $vehicleId, $body);
    }

    /**
     * Corrects cost and notes only.
     *
     * @param  array<string, mixed>  $data  validated body
     * @return array<string, mixed>
     */
    public function update(string $userId, string $vehicleId, string $maintenanceId, array $data): array
    {
        return $this->vehicles->updateMaintenance($userId, $vehicleId, $maintenanceId, Arr::only($data, ['cost', 'notes']));
    }

    /** @return array<int, array<string, mixed>> */
    public function expenses(string $userId, string $vehicleId): array
    {
        return $this->vehicles->listExpenses($userId, $vehicleId);
    }
}
