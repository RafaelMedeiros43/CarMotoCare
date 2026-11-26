<?php

declare(strict_types=1);

namespace App\Clients;

use App\Exceptions\VehiclesApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP da SAPI Veículos (docs/api/sapi-vehicles.yaml). Os microserviços não enviam X-User-Id.
 */
final class VehiclesApiClient
{
    /**
     * GET /obd-devices/{deviceId}
     *
     * @return array{deviceId: string, vehicleId: string, userId: string}|null null se o dispositivo não estiver emparelhado (404)
     *
     * @throws VehiclesApiException
     */
    public function findDevice(string $deviceId): ?array
    {
        $response = $this->send(fn (): Response => $this->http()->get('/obd-devices/' . rawurlencode($deviceId)));

        if ($response->status() === 404) {
            return null;
        }

        $this->ensureSuccessful($response, 'GET /obd-devices/{deviceId}');

        $data = $response->json();

        if (! is_array($data) || ! is_string($data['vehicleId'] ?? null) || ! is_string($data['userId'] ?? null)) {
            throw new VehiclesApiException('GET /obd-devices/{deviceId} devolveu uma resposta inválida.');
        }

        return [
            'deviceId' => is_string($data['deviceId'] ?? null) ? $data['deviceId'] : $deviceId,
            'vehicleId' => $data['vehicleId'],
            'userId' => $data['userId'],
        ];
    }

    /**
     * GET /vehicles/{vehicleId}/readings/latest (sem leituras devolve 200 com tudo nulo)
     *
     * @return array{km: int|null, readAt: string|null}
     *
     * @throws VehiclesApiException
     */
    public function getLatestReading(string $vehicleId): array
    {
        $response = $this->send(
            fn (): Response => $this->http()->get('/vehicles/' . rawurlencode($vehicleId) . '/readings/latest'),
        );

        $this->ensureSuccessful($response, 'GET /vehicles/{vehicleId}/readings/latest');

        $data = $response->json();

        if (! is_array($data)) {
            throw new VehiclesApiException('GET /vehicles/{vehicleId}/readings/latest devolveu uma resposta inválida.');
        }

        return [
            'km' => is_int($data['km'] ?? null) ? $data['km'] : null,
            'readAt' => is_string($data['readAt'] ?? null) ? $data['readAt'] : null,
        ];
    }

    /**
     * POST /vehicles/{vehicleId}/readings (schema ReadingCreate)
     *
     * @param array{deviceId: string, readAt: string, km: int|null, oilTemp: int|float|null, gps: array{latitude: int|float, longitude: int|float}|null} $reading
     *
     * @throws VehiclesApiException
     */
    public function createReading(string $vehicleId, array $reading): void
    {
        $response = $this->send(
            fn (): Response => $this->http()->post('/vehicles/' . rawurlencode($vehicleId) . '/readings', $reading),
        );

        $this->ensureSuccessful($response, 'POST /vehicles/{vehicleId}/readings');
    }

    /**
     * GET /vehicles/{vehicleId} -> km atual do veículo.
     *
     * @throws VehiclesApiException
     */
    public function getVehicleKm(string $vehicleId): int
    {
        $response = $this->send(fn (): Response => $this->http()->get('/vehicles/' . rawurlencode($vehicleId)));

        $this->ensureSuccessful($response, 'GET /vehicles/{vehicleId}');

        $km = $response->json('km');

        if (! is_int($km)) {
            throw new VehiclesApiException('GET /vehicles/{vehicleId} devolveu uma resposta sem km válida.');
        }

        return $km;
    }

    /**
     * PUT /vehicles/{vehicleId}/mileage (schemas MileageUpdate / MileageResult)
     *
     * @return array{km: int, previousKm: int, nextAlertKm: int|null}|null null se a SAPI devolver 409 (km igual ou inferior)
     *
     * @throws VehiclesApiException
     */
    public function updateMileage(string $vehicleId, int $km): ?array
    {
        $response = $this->send(
            fn (): Response => $this->http()->put('/vehicles/' . rawurlencode($vehicleId) . '/mileage', ['km' => $km]),
        );

        if ($response->status() === 409) {
            return null;
        }

        $this->ensureSuccessful($response, 'PUT /vehicles/{vehicleId}/mileage');

        $data = $response->json();

        if (
            ! is_array($data)
            || ! is_int($data['km'] ?? null)
            || ! is_int($data['previousKm'] ?? null)
            || ! array_key_exists('nextAlertKm', $data)
            || ($data['nextAlertKm'] !== null && ! is_int($data['nextAlertKm']))
        ) {
            throw new VehiclesApiException('PUT /vehicles/{vehicleId}/mileage devolveu uma resposta inválida.');
        }

        return [
            'km' => $data['km'],
            'previousKm' => $data['previousKm'],
            'nextAlertKm' => $data['nextAlertKm'],
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.sapi_vehicles.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(max(1, (int) config('services.sapi_vehicles.timeout', 5)));
    }

    /**
     * @param callable(): Response $request
     *
     * @throws VehiclesApiException
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new VehiclesApiException(
                'Não foi possível contactar a SAPI Veículos: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * @throws VehiclesApiException
     */
    private function ensureSuccessful(Response $response, string $operation): void
    {
        if (! $response->successful()) {
            throw new VehiclesApiException(
                sprintf('%s devolveu o estado HTTP %d.', $operation, $response->status()),
                $response->status(),
            );
        }
    }
}
