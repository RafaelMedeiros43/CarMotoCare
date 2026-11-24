<?php

namespace App\Services;

use App\Clients\SapiVehiclesClient;
use Illuminate\Support\Arr;

/**
 * Vehicles of the logged-in user. The data lives in the sapi-vehicles; this service only orchestrates.
 * The user id always comes from the JWT (never from the request), so a user only reaches their own vehicles.
 */
class VehicleService
{
    /** Fields the sapi-vehicles accepts in POST /vehicles. */
    private const CREATE_FIELDS = ['type', 'brand', 'model', 'firstRegistration', 'plate', 'km'];

    /** Fields the sapi-vehicles accepts in PATCH /vehicles/{vehicleId} (km and type are never changed). */
    private const UPDATE_FIELDS = ['brand', 'model', 'firstRegistration', 'plate'];

    public function __construct(private readonly SapiVehiclesClient $vehicles) {}

    /**
     * @param  array{type?: string, limit?: int|string, offset?: int|string}  $filters
     * @return array{vehicles: array<int, array<string, mixed>>, total: int}
     */
    public function list(string $userId, array $filters): array
    {
        return $this->vehicles->listVehicles($userId, $filters);
    }

    /**
     * Creates the vehicle and its default part plan in the sapi-vehicles.
     *
     * TODO (prazos): iucValidUntil, insuranceValidUntil and inspectionValidUntil are accepted by the
     * endpoint but dropped here. When POST /vehicles/{vehicleId}/deadlines exists in the sapi-vehicles,
     * create one pending deadline per date given.
     *
     * @param  array<string, mixed>  $data  validated body
     * @return array<string, mixed>
     */
    public function create(string $userId, array $data): array
    {
        return $this->vehicles->createVehicle($userId, Arr::only($data, self::CREATE_FIELDS));
    }

    /** @return array<string, mixed> */
    public function find(string $userId, string $vehicleId): array
    {
        return $this->vehicles->getVehicle($userId, $vehicleId);
    }

    /**
     * @param  array<string, mixed>  $data  validated body
     * @return array<string, mixed>
     */
    public function update(string $userId, string $vehicleId, array $data): array
    {
        return $this->vehicles->updateVehicle($userId, $vehicleId, Arr::only($data, self::UPDATE_FIELDS));
    }

    public function delete(string $userId, string $vehicleId): void
    {
        $this->vehicles->deleteVehicle($userId, $vehicleId);
    }
}
