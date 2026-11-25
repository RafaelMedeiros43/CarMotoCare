<?php

namespace App\Services;

use App\Clients\SapiVehiclesClient;

/**
 * Parts of the maintenance plan of the logged-in user's vehicles. The data and the calculated values come from
 * the sapi-vehicles; the user id always comes from the JWT, so a user only reaches their own vehicles.
 */
class PartService
{
    public function __construct(private readonly SapiVehiclesClient $vehicles) {}

    /** @return array<int, array<string, mixed>> */
    public function listForVehicle(string $userId, string $vehicleId): array
    {
        return $this->vehicles->listParts($userId, $vehicleId);
    }
}
