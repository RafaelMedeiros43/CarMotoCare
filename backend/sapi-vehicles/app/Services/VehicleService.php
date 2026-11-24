<?php

namespace App\Services;

use App\Exceptions\ProblemException;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Business rules for vehicles. nextService and openAlerts are calculated, never stored (D6).
 */
class VehicleService
{
    /** Contract field (camelCase) => column (snake_case). */
    private const COLUMNS = [
        'type' => 'type',
        'brand' => 'brand',
        'model' => 'model',
        'firstRegistration' => 'first_registration',
        'plate' => 'plate',
        'km' => 'km',
    ];

    /**
     * Vehicles of a user, oldest first, with nextService data and the open alerts count.
     * Pagination is applied in the query; the total ignores it.
     *
     * @return array{0: Collection<int, Vehicle>, 1: int}  the page and the total
     */
    public function list(string $userId, ?string $type, int $limit, int $offset): array
    {
        $query = Vehicle::query()
            ->where('user_id', $userId)
            ->when($type !== null, fn ($q) => $q->where('type', $type));

        $total = (clone $query)->count();

        $vehicles = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->offset($offset)
            ->with('parts')
            ->withCount(['alerts as open_alerts_count' => fn ($q) => $q->where('status', 'ABERTO')])
            ->get();

        return [$vehicles, $total];
    }

    /** Creates the vehicle and its default part plan (config/default_parts.php) in one transaction. */
    public function create(string $userId, array $data): Vehicle
    {
        try {
            $vehicle = DB::transaction(function () use ($userId, $data) {
                $vehicle = Vehicle::create(['user_id' => $userId] + $this->columns($data));

                // Last service = current km and today, otherwise every part would be overdue from day one.
                $today = now()->toDateString();
                foreach (config("default_parts.{$vehicle->type}") as $part) {
                    $vehicle->parts()->create($part + [
                        'last_service_km' => $vehicle->km,
                        'last_service_date' => $today,
                    ]);
                }

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicatePlate();
        }

        return $this->withDerivedData($vehicle);
    }

    /** @param  ?string  $userId  if given, the vehicle must belong to this user (otherwise 404) */
    public function find(string $vehicleId, ?string $userId = null): Vehicle
    {
        return $this->withDerivedData($this->findOwned($vehicleId, $userId));
    }

    /** Only the descriptive fields; km and type are never changed here. */
    public function update(string $vehicleId, ?string $userId, array $data): Vehicle
    {
        $vehicle = $this->findOwned($vehicleId, $userId);

        try {
            $vehicle->update($this->columns($data, only: ['brand', 'model', 'firstRegistration', 'plate']));
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicatePlate();
        }

        return $this->withDerivedData($vehicle);
    }

    /** Everything that belongs to the vehicle is deleted by the database (R10). */
    public function delete(string $vehicleId, ?string $userId = null): void
    {
        $this->findOwned($vehicleId, $userId)->delete();
    }

    private function findOwned(string $vehicleId, ?string $userId): Vehicle
    {
        return Vehicle::query()
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->findOrFail($vehicleId);
    }

    private function withDerivedData(Vehicle $vehicle): Vehicle
    {
        return $vehicle
            ->load('parts')
            ->loadCount(['alerts as open_alerts_count' => fn ($q) => $q->where('status', 'ABERTO')]);
    }

    /**
     * @param  array<string, mixed>  $data  validated data, camelCase
     * @param  array<int, string>|null  $only  contract fields allowed
     * @return array<string, mixed>
     */
    private function columns(array $data, ?array $only = null): array
    {
        $columns = [];
        foreach (self::COLUMNS as $field => $column) {
            if (($only === null || in_array($field, $only, true)) && array_key_exists($field, $data)) {
                $columns[$column] = $data[$field];
            }
        }

        return $columns;
    }

    /** Race between the validation and the insert: same answer as the validation rule. */
    private function duplicatePlate(): ProblemException
    {
        return new ProblemException(400, 'Pedido inválido', 'Há campos inválidos.', [
            ['field' => 'plate', 'message' => 'Já existe um veículo com esta matrícula.'],
        ]);
    }
}
