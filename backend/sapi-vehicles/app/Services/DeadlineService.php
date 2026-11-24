<?php

namespace App\Services;

use App\Exceptions\ProblemException;
use App\Models\Deadline;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Legal deadlines (IUC, SEGURO, INSPECAO) of a vehicle.
 * R2: a vehicle has at most one PENDENTE deadline per type.
 */
class DeadlineService
{
    /**
     * Deadlines of a vehicle: the pending ones first (nearest date first), then the paid ones (most recent first).
     *
     * @param  ?string  $userId  if given, the vehicle must belong to this user (otherwise 404)
     * @return Collection<int, Deadline>
     */
    public function listForVehicle(string $vehicleId, ?string $userId = null): Collection
    {
        $vehicle = $this->findVehicle($vehicleId, $userId);

        $pending = $vehicle->deadlines()->where('status', 'PENDENTE')->orderBy('due_date')->orderBy('id')->get();
        $paid = $vehicle->deadlines()->where('status', '<>', 'PENDENTE')->orderByDesc('due_date')->orderBy('id')->get();

        return $pending->concat($paid)->values();
    }

    /**
     * Creates the pending deadline of a type (409 if there is already one: R2).
     *
     * @param  array{type: string, dueDate: string}  $data
     */
    public function create(string $vehicleId, ?string $userId, array $data): Deadline
    {
        $vehicle = $this->findVehicle($vehicleId, $userId);

        try {
            return DB::transaction(function () use ($vehicle, $data) {
                $exists = $vehicle->deadlines()->where('type', $data['type'])->where('status', 'PENDENTE')->exists();
                if ($exists) {
                    throw $this->pendingExists();
                }

                return $vehicle->deadlines()->create([
                    'type' => $data['type'],
                    'status' => 'PENDENTE',
                    'due_date' => $data['dueDate'],
                ])->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests at the same time: the partial unique index (R2) stopped the second one.
            throw $this->pendingExists();
        }
    }

    /**
     * Pending deadlines of every vehicle, nearest date first (for the alerts microservice).
     *
     * @return Collection<int, Deadline>
     */
    public function listPending(?string $type, ?string $dueBefore): Collection
    {
        return Deadline::query()
            ->where('status', 'PENDENTE')
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->when($dueBefore !== null, fn ($q) => $q->where('due_date', '<=', $dueBefore))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    private function findVehicle(string $vehicleId, ?string $userId): Vehicle
    {
        return Vehicle::query()
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->findOrFail($vehicleId);
    }

    private function pendingExists(): ProblemException
    {
        return new ProblemException(409, 'Conflito', 'Já existe um prazo pendente deste tipo para este veículo.');
    }
}
