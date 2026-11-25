<?php

namespace App\Clients;

use App\Exceptions\ProblemException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the sapi-vehicles System API (docs/api/sapi-vehicles.yaml).
 *
 * Every call sends X-User-Id (the user id taken from the JWT by the middleware). The SAPI trusts it.
 * Problem responses from the SAPI (400, 404, 409) are rethrown as ProblemException with the same status,
 * title, detail and field errors, so the browser sees the SAPI's message.
 * Anything else (SAPI down, timeout, 5xx) becomes a 502.
 */
class SapiVehiclesClient
{
    /** Statuses the SAPI uses for business errors; they are passed on to the browser as they are. */
    private const PASSTHROUGH_STATUSES = [400, 404, 409];

    /**
     * GET /vehicles
     *
     * @param  array{type?: string, limit?: int|string, offset?: int|string}  $query
     * @return array{vehicles: array<int, array<string, mixed>>, total: int}
     */
    public function listVehicles(string $userId, array $query = []): array
    {
        $response = $this->send($userId, fn (PendingRequest $http) => $http->get('/vehicles', $query));
        $vehicles = $response->json() ?? [];

        return [
            'vehicles' => $vehicles,
            'total' => (int) ($response->header('X-Total-Count') ?: count($vehicles)),
        ];
    }

    /**
     * POST /vehicles
     *
     * @param  array{type: string, brand: string, model: string, firstRegistration: string, plate: string, km: int}  $data
     * @return array<string, mixed>
     */
    public function createVehicle(string $userId, array $data): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->post('/vehicles', $data))->json() ?? [];
    }

    /**
     * GET /vehicles/{vehicleId}
     *
     * @return array<string, mixed>
     */
    public function getVehicle(string $userId, string $vehicleId): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->get($this->vehiclePath($vehicleId)))->json() ?? [];
    }

    /**
     * PATCH /vehicles/{vehicleId}
     *
     * @param  array{brand?: string, model?: string, firstRegistration?: string, plate?: string}  $data
     * @return array<string, mixed>
     */
    public function updateVehicle(string $userId, string $vehicleId, array $data): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->patch($this->vehiclePath($vehicleId), $data))->json() ?? [];
    }

    /** DELETE /vehicles/{vehicleId} (204) */
    public function deleteVehicle(string $userId, string $vehicleId): void
    {
        $this->send($userId, fn (PendingRequest $http) => $http->delete($this->vehiclePath($vehicleId)));
    }

    /**
     * GET /vehicles/{vehicleId}/deadlines
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDeadlines(string $userId, string $vehicleId): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->get($this->vehiclePath($vehicleId).'/deadlines'))->json() ?? [];
    }

    /**
     * POST /vehicles/{vehicleId}/deadlines (409 if the type already has a pending deadline)
     *
     * @param  array{type: string, dueDate: string}  $data
     * @return array<string, mixed>
     */
    public function createDeadline(string $userId, string $vehicleId, array $data): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->post($this->vehiclePath($vehicleId).'/deadlines', $data))->json() ?? [];
    }

    /**
     * GET /vehicles/{vehicleId}/maintenances
     *
     * @return array<int, array<string, mixed>>
     */
    public function listMaintenances(string $userId, string $vehicleId): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->get($this->vehiclePath($vehicleId).'/maintenances'))->json() ?? [];
    }

    /**
     * POST /vehicles/{vehicleId}/maintenances (the SAPI updates the parts and closes their alerts)
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>  the maintenance plus closedAlertIds
     */
    public function createMaintenance(string $userId, string $vehicleId, array $data): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->post($this->vehiclePath($vehicleId).'/maintenances', $data))->json() ?? [];
    }

    /**
     * GET /vehicles/{vehicleId}/maintenances/{maintenanceId}
     *
     * @return array<string, mixed>
     */
    public function getMaintenance(string $userId, string $vehicleId, string $maintenanceId): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->get($this->maintenancePath($vehicleId, $maintenanceId)))->json() ?? [];
    }

    /**
     * PATCH /vehicles/{vehicleId}/maintenances/{maintenanceId}
     *
     * @param  array{cost?: float|int, notes?: ?string}  $data
     * @return array<string, mixed>
     */
    public function updateMaintenance(string $userId, string $vehicleId, string $maintenanceId, array $data): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->patch($this->maintenancePath($vehicleId, $maintenanceId), $data))->json() ?? [];
    }

    /**
     * GET /vehicles/{vehicleId}/expenses
     *
     * @return array<int, array<string, mixed>>
     */
    public function listExpenses(string $userId, string $vehicleId): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->get($this->vehiclePath($vehicleId).'/expenses'))->json() ?? [];
    }

    /**
     * GET /vehicles/{vehicleId}/parts
     *
     * @return array<int, array<string, mixed>>
     */
    public function listParts(string $userId, string $vehicleId): array
    {
        return $this->send($userId, fn (PendingRequest $http) => $http->get($this->vehiclePath($vehicleId).'/parts'))->json() ?? [];
    }

    /**
     * PATCH /vehicles/{vehicleId}/parts/{partId}
     *
     * @param  array{intervalKm?: ?int, intervalMonths?: ?int, lastServiceDate?: string, lastServiceKm?: int}  $data
     * @return array<string, mixed>
     */
    public function updatePart(string $userId, string $vehicleId, string $partId, array $data): array
    {
        $path = $this->vehiclePath($vehicleId).'/parts/'.rawurlencode($partId);

        return $this->send($userId, fn (PendingRequest $http) => $http->patch($path, $data))->json() ?? [];
    }

    private function maintenancePath(string $vehicleId, string $maintenanceId): string
    {
        return $this->vehiclePath($vehicleId).'/maintenances/'.rawurlencode($maintenanceId);
    }

    private function vehiclePath(string $vehicleId): string
    {
        return '/vehicles/'.rawurlencode($vehicleId);
    }

    /** @param  callable(PendingRequest): Response  $call */
    private function send(string $userId, callable $call): Response
    {
        $http = Http::baseUrl(rtrim((string) config('services.sapi_vehicles.url'), '/'))
            ->acceptJson()
            ->withHeaders(['X-User-Id' => $userId])
            ->connectTimeout(2)
            ->timeout(5);

        try {
            $response = $call($http);
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        if ($response->successful()) {
            return $response;
        }

        if (in_array($response->status(), self::PASSTHROUGH_STATUSES, true)) {
            $problem = $response->json();
            $problem = is_array($problem) ? $problem : [];

            throw new ProblemException(
                $response->status(),
                (string) ($problem['title'] ?? 'Erro'),
                isset($problem['detail']) ? (string) $problem['detail'] : null,
                is_array($problem['errors'] ?? null) ? $problem['errors'] : [],
            );
        }

        // 5xx or an unexpected status: the SAPI is not working as its contract says.
        report("sapi-vehicles returned an unexpected status {$response->status()}");

        throw $this->unavailable();
    }

    private function unavailable(): ProblemException
    {
        return new ProblemException(
            502,
            'Serviço indisponível',
            'O serviço de veículos não está disponível. Tente novamente dentro de instantes.',
        );
    }
}
