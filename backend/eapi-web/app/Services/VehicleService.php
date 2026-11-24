<?php

namespace App\Services;

use App\Clients\SapiVehiclesClient;
use App\Exceptions\ProblemException;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Vehicles and their deadlines for the logged-in user. The data lives in the sapi-vehicles; this service only
 * orchestrates. The user id always comes from the JWT (never from the request), so a user only reaches
 * their own vehicles.
 */
class VehicleService
{
    /** Fields the sapi-vehicles accepts in POST /vehicles. */
    private const CREATE_FIELDS = ['type', 'brand', 'model', 'firstRegistration', 'plate', 'km'];

    /** Fields the sapi-vehicles accepts in PATCH /vehicles/{vehicleId} (km and type are never changed). */
    private const UPDATE_FIELDS = ['brand', 'model', 'firstRegistration', 'plate'];

    /** Optional validity dates of POST /vehicles => type of the pending deadline created for each one. */
    private const DEADLINE_DATES = [
        'iucValidUntil' => 'IUC',
        'insuranceValidUntil' => 'SEGURO',
        'inspectionValidUntil' => 'INSPECAO',
    ];

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
     * Creates the vehicle and its default part plan in the sapi-vehicles, then one pending deadline for each
     * validity date given (iucValidUntil, insuranceValidUntil, inspectionValidUntil).
     *
     * The two steps are not one transaction. If a deadline cannot be created the vehicle is deleted again, so
     * the user is not left with a half-created vehicle, and the error of the deadline is returned.
     *
     * @param  array<string, mixed>  $data  validated body
     * @return array<string, mixed>
     */
    public function create(string $userId, array $data): array
    {
        $vehicle = $this->vehicles->createVehicle($userId, Arr::only($data, self::CREATE_FIELDS));

        try {
            foreach (self::DEADLINE_DATES as $field => $type) {
                if (! empty($data[$field])) {
                    $this->vehicles->createDeadline($userId, $vehicle['id'], ['type' => $type, 'dueDate' => $data[$field]]);
                }
            }
        } catch (ProblemException $e) {
            $this->removeQuietly($userId, $vehicle['id']);

            throw $e;
        }

        return $vehicle;
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

    /** @return array<int, array<string, mixed>> */
    public function listDeadlines(string $userId, string $vehicleId): array
    {
        return $this->vehicles->listDeadlines($userId, $vehicleId);
    }

    /**
     * @param  array{type: string, dueDate: string}  $data  validated body
     * @return array<string, mixed>
     */
    public function createDeadline(string $userId, string $vehicleId, array $data): array
    {
        return $this->vehicles->createDeadline($userId, $vehicleId, Arr::only($data, ['type', 'dueDate']));
    }

    /** Undo for create(): a failure here is only reported, because the caller is already answering an error. */
    private function removeQuietly(string $userId, string $vehicleId): void
    {
        try {
            $this->vehicles->deleteVehicle($userId, $vehicleId);
        } catch (Throwable $e) {
            report("Could not remove vehicle {$vehicleId} after a failed deadline: {$e->getMessage()}");
        }
    }
}
