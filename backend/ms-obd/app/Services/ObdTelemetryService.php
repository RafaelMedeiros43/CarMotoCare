<?php

declare(strict_types=1);

namespace App\Services;

use App\Clients\VehiclesApiClient;
use App\Contracts\MqttPublisherInterface;
use App\Exceptions\InvalidTelemetryException;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Processa uma leitura recebida em obd/telemetry/{deviceId} (docs/mqtt/mqtt-contract.md, secção 3).
 */
final class ObdTelemetryService
{
    private const MAX_FUTURE_SECONDS = 60;

    private const MAX_AGE_SECONDS = 86400;

    private const ISO_8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/';

    public function __construct(
        private readonly VehiclesApiClient $vehicles,
        private readonly MqttPublisherInterface $publisher,
    ) {
    }

    /**
     * Nunca lança exceções: qualquer falha fica em log e o consumidor continua a correr.
     */
    public function handle(string $topic, string $message): TelemetryOutcome
    {
        $topicDeviceId = $this->deviceIdFromTopic($topic);

        if ($topicDeviceId === null) {
            Log::warning('Leitura OBD descartada: tópico inesperado.', ['topic' => $topic]);

            return TelemetryOutcome::Discarded;
        }

        try {
            return $this->process($topicDeviceId, $message);
        } catch (InvalidTelemetryException $e) {
            Log::warning(
                sprintf('Leitura OBD descartada (%s): %s', $e->rule, $e->getMessage()),
                ['deviceId' => $topicDeviceId, 'topic' => $topic],
            );

            return TelemetryOutcome::Discarded;
        } catch (Throwable $e) {
            Log::error(
                sprintf('Falha ao processar a leitura OBD: %s', $e->getMessage()),
                ['deviceId' => $topicDeviceId, 'topic' => $topic, 'exception' => $e::class],
            );

            return TelemetryOutcome::Failed;
        }
    }

    /**
     * @throws InvalidTelemetryException
     * @throws Throwable
     */
    private function process(string $topicDeviceId, string $message): TelemetryOutcome
    {
        // V1, V2 e V3 (não precisam da SAPI).
        $reading = $this->validate($topicDeviceId, $message);

        // V4: dispositivo emparelhado.
        $device = $this->vehicles->findDevice($topicDeviceId);

        if ($device === null) {
            Log::info('Leitura OBD descartada (V4): dispositivo não emparelhado.', ['deviceId' => $topicDeviceId]);

            return TelemetryOutcome::Discarded;
        }

        $vehicleId = $device['vehicleId'];
        $km = $reading['km'];

        // V5: salto de km plausível.
        if ($km !== null && ! $this->isPlausibleKmJump($vehicleId, $topicDeviceId, $km, $reading['readAtTimestamp'])) {
            return TelemetryOutcome::Discarded;
        }

        // 1. Guarda sempre a leitura validada.
        $this->vehicles->createReading($vehicleId, [
            'deviceId' => $topicDeviceId,
            'readAt' => $reading['readAt'],
            'km' => $km,
            'oilTemp' => $reading['oilTemp'],
            'gps' => $reading['gps'],
        ]);

        if ($km === null) {
            return TelemetryOutcome::Stored;
        }

        // 2. Só atualiza a km se esta for maior do que a do veículo.
        if ($km <= $this->vehicles->getVehicleKm($vehicleId)) {
            return TelemetryOutcome::Stored;
        }

        $mileage = $this->vehicles->updateMileage($vehicleId, $km);

        if ($mileage === null) {
            // 409: a km do veículo já é igual ou superior (ex.: leitura repetida por QoS 1).
            return TelemetryOutcome::Stored;
        }

        // 3. A km subiu: publica o novo estado do veículo.
        $this->publisher->publish(
            (string) config('mqtt.topics.vehicle_state', 'app/vehicle/state'),
            json_encode([
                'vehicleId' => $vehicleId,
                'userId' => $device['userId'],
                'km' => $mileage['km'],
                'previousKm' => $mileage['previousKm'],
                'nextAlertKm' => $mileage['nextAlertKm'],
                'readAt' => $reading['readAt'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            1,
            false,
        );

        return TelemetryOutcome::Published;
    }

    private function deviceIdFromTopic(string $topic): ?string
    {
        $prefix = (string) config('mqtt.topics.telemetry_prefix', 'obd/telemetry/');

        if (! str_starts_with($topic, $prefix)) {
            return null;
        }

        $deviceId = substr($topic, strlen($prefix));

        if ($deviceId === '' || str_contains($deviceId, '/') || str_contains($deviceId, '+') || str_contains($deviceId, '#')) {
            return null;
        }

        return $deviceId;
    }

    /**
     * Regras V1, V2 e V3.
     *
     * @return array{
     *     readAt: string,
     *     readAtTimestamp: int,
     *     km: int|null,
     *     oilTemp: int|float|null,
     *     gps: array{latitude: int|float, longitude: int|float}|null
     * }
     *
     * @throws InvalidTelemetryException
     */
    private function validate(string $topicDeviceId, string $message): array
    {
        // V1
        try {
            $data = json_decode($message, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidTelemetryException('V1', 'JSON inválido.');
        }

        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new InvalidTelemetryException('V1', 'o payload não é um objeto JSON.');
        }

        if (($data['deviceId'] ?? null) !== $topicDeviceId) {
            throw new InvalidTelemetryException('V1', 'o deviceId do payload é diferente do deviceId do tópico.');
        }

        // V2
        $readAt = $this->parseReadAt($data['readAt'] ?? null);

        // V3
        return [
            'readAt' => $readAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'readAtTimestamp' => $readAt->getTimestamp(),
            'km' => $this->parseKm($data['km'] ?? null),
            'oilTemp' => $this->parseOilTemp($data['oilTemp'] ?? null),
            'gps' => $this->parseGps($data['gps'] ?? null),
        ];
    }

    /**
     * @throws InvalidTelemetryException
     */
    private function parseReadAt(mixed $value): DateTimeImmutable
    {
        if (! is_string($value) || preg_match(self::ISO_8601, $value) !== 1) {
            throw new InvalidTelemetryException('V2', 'readAt ausente ou fora do formato ISO 8601.');
        }

        try {
            $readAt = new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new InvalidTelemetryException('V2', 'readAt não é uma data válida.');
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new InvalidTelemetryException('V2', 'readAt não é uma data válida.');
        }

        $now = Carbon::now()->getTimestamp();
        $timestamp = $readAt->getTimestamp();

        if ($timestamp > $now + self::MAX_FUTURE_SECONDS) {
            throw new InvalidTelemetryException('V2', 'readAt está mais de 1 minuto no futuro.');
        }

        if ($timestamp < $now - self::MAX_AGE_SECONDS) {
            throw new InvalidTelemetryException('V2', 'readAt tem mais de 24 horas.');
        }

        return $readAt;
    }

    /**
     * @throws InvalidTelemetryException
     */
    private function parseKm(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            throw new InvalidTelemetryException('V3', 'km tem de ser um inteiro >= 0.');
        }

        return $value;
    }

    /**
     * @throws InvalidTelemetryException
     */
    private function parseOilTemp(mixed $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        if ((! is_int($value) && ! is_float($value)) || $value < -40 || $value > 200) {
            throw new InvalidTelemetryException('V3', 'oilTemp tem de ser um número entre -40 e 200.');
        }

        return $value;
    }

    /**
     * @return array{latitude: int|float, longitude: int|float}|null
     *
     * @throws InvalidTelemetryException
     */
    private function parseGps(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidTelemetryException('V3', 'gps tem de ser um objeto com latitude e longitude.');
        }

        $latitude = $value['latitude'] ?? null;
        $longitude = $value['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            throw new InvalidTelemetryException('V3', 'gps tem de ter latitude e longitude (nunca só uma).');
        }

        if ((! is_int($latitude) && ! is_float($latitude)) || $latitude < -90 || $latitude > 90) {
            throw new InvalidTelemetryException('V3', 'latitude tem de estar entre -90 e 90.');
        }

        if ((! is_int($longitude) && ! is_float($longitude)) || $longitude < -180 || $longitude > 180) {
            throw new InvalidTelemetryException('V3', 'longitude tem de estar entre -180 e 180.');
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    /**
     * V5: o aumento de km tem de caber na velocidade máxima plausível durante o tempo desde a leitura anterior.
     * Sem leitura anterior com km e readAt, a regra não se aplica. Diminuições de km não são julgadas aqui
     * (a SAPI recusa-as com 409).
     *
     * @throws Throwable
     */
    private function isPlausibleKmJump(string $vehicleId, string $deviceId, int $km, int $readAtTimestamp): bool
    {
        $previous = $this->vehicles->getLatestReading($vehicleId);

        if ($previous['km'] === null || $previous['readAt'] === null) {
            return true;
        }

        $increase = $km - $previous['km'];

        if ($increase <= 0) {
            return true;
        }

        try {
            $previousTimestamp = (new DateTimeImmutable($previous['readAt']))->getTimestamp();
        } catch (Throwable) {
            return true;
        }

        $elapsedSeconds = max(0, $readAtTimestamp - $previousTimestamp);
        $maxIncrease = (float) config('mqtt.max_speed_kmh', 250) * ($elapsedSeconds / 3600);

        if ($increase <= $maxIncrease) {
            return true;
        }

        Log::warning(
            sprintf(
                'Leitura OBD descartada (V5): salto de km implausível (+%d km em %d s, máximo %.2f km).',
                $increase,
                $elapsedSeconds,
                $maxIncrease,
            ),
            ['deviceId' => $deviceId, 'vehicleId' => $vehicleId],
        );

        return false;
    }
}
