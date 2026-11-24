<?php

declare(strict_types=1);

/**
 * CarMotoCare OBD device simulator.
 *
 * Publishes a reading to obd/telemetry/{DEVICE_ID} (QoS 1, retain = false) every INTERVAL_S seconds,
 * as per sections 3 and 7 of docs/mqtt/mqtt-contract.md. One process per device.
 *
 * Environment variables: see obd-simulator/README.md.
 */

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

require __DIR__ . '/vendor/autoload.php';

final class ConfigException extends RuntimeException
{
}

/**
 * Configuration read from environment variables (validated, immutable).
 */
final class Config
{
    public const MODE_NORMAL = 'normal';
    public const MODE_INVALID_KM = 'invalid-km';
    public const MODE_BAD_PAYLOAD = 'bad-payload';

    public const MODES = [self::MODE_NORMAL, self::MODE_INVALID_KM, self::MODE_BAD_PAYLOAD];

    public function __construct(
        public readonly string $deviceId,
        public readonly string $host,
        public readonly int $port,
        public readonly string $username,
        public readonly string $password,
        public readonly bool $useTls,
        public readonly ?string $tlsCaFile,
        public readonly int $startKm,
        public readonly float $intervalS,
        public readonly float $speedFactor,
        public readonly string $mode,
    ) {
    }

    public function topic(): string
    {
        return 'obd/telemetry/' . $this->deviceId;
    }

    /**
     * @param array<string, mixed> $env usually getenv()
     *
     * @throws ConfigException
     */
    public static function fromEnv(array $env): self
    {
        $get = static function (string $name) use ($env): ?string {
            $value = $env[$name] ?? null;
            if (!is_string($value)) {
                return null;
            }
            $value = trim($value);

            return $value === '' ? null : $value;
        };

        $deviceId = $get('DEVICE_ID');
        if ($deviceId === null) {
            throw new ConfigException('DEVICE_ID is required (e.g. DEVICE_ID=OBD-7F3A-0001).');
        }
        // The deviceId is part of the topic: it must not contain MQTT separators or wildcards.
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $deviceId) !== 1) {
            throw new ConfigException(
                'Invalid DEVICE_ID: use 1 to 64 characters [A-Za-z0-9_.-], starting with a letter or digit.'
            );
        }

        $port = self::parseInt($get('MQTT_PORT') ?? '1883', 'MQTT_PORT', 1, 65535);

        $useTlsRaw = $get('MQTT_USE_TLS');
        $useTls = $useTlsRaw === null ? $port === 8883 : self::parseBool($useTlsRaw, 'MQTT_USE_TLS');

        $startKm = self::parseInt($get('START_KM') ?? '68420', 'START_KM', 0, 2_000_000_000);

        $intervalS = self::parseFloat($get('INTERVAL_S') ?? '5', 'INTERVAL_S');
        if ($intervalS <= 0.0 || $intervalS > 3600.0) {
            throw new ConfigException('INTERVAL_S must be between 0 (exclusive) and 3600 seconds.');
        }

        $speedFactor = self::parseFloat($get('SPEED_FACTOR') ?? '1', 'SPEED_FACTOR');
        if ($speedFactor < 0.0 || $speedFactor > 100000.0) {
            throw new ConfigException('SPEED_FACTOR must be between 0 and 100000.');
        }

        $mode = strtolower($get('MODE') ?? self::MODE_NORMAL);
        if (!in_array($mode, self::MODES, true)) {
            throw new ConfigException(
                sprintf('Invalid MODE "%s": use %s.', $mode, implode(', ', self::MODES))
            );
        }

        // The password is not trimmed: it may legitimately contain spaces.
        $password = isset($env['MQTT_PASSWORD']) && is_string($env['MQTT_PASSWORD']) ? $env['MQTT_PASSWORD'] : '';

        return new self(
            deviceId: $deviceId,
            host: $get('MQTT_HOST') ?? 'rabbitmq',
            port: $port,
            username: $get('MQTT_USERNAME') ?? $deviceId,
            password: $password,
            useTls: $useTls,
            tlsCaFile: $get('MQTT_TLS_CA_FILE'),
            startKm: $startKm,
            intervalS: $intervalS,
            speedFactor: $speedFactor,
            mode: $mode,
        );
    }

    private static function parseInt(string $value, string $name, int $min, int $max): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($parsed === false) {
            throw new ConfigException(sprintf('Invalid %s "%s": integer between %d and %d.', $name, $value, $min, $max));
        }

        return $parsed;
    }

    private static function parseFloat(string $value, string $name): float
    {
        $parsed = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($parsed === false || !is_finite($parsed)) {
            throw new ConfigException(sprintf('Invalid %s "%s": number expected.', $name, $value));
        }

        return $parsed;
    }

    private static function parseBool(string $value, string $name): bool
    {
        return match (strtolower($value)) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new ConfigException(sprintf('Invalid %s "%s": use true or false.', $name, $value)),
        };
    }
}

/**
 * State of the "car" (km, oil temperature, GPS) and generation of valid readings (section 3 of the contract).
 */
final class TelemetryGenerator
{
    private float $km;
    private float $oilTemp = 20.0;                 // cold engine
    private float $latitude = 37.7417;             // Ponta Delgada
    private float $longitude = -25.6710;

    public function __construct(private readonly Config $config)
    {
        $this->km = (float) $config->startKm;
    }

    /**
     * Advances the state by one interval and returns the corresponding reading.
     *
     * @return array{deviceId: string, readAt: string, km: int, oilTemp: float, gps: array{latitude: float, longitude: float}}
     */
    public function next(int $now): array
    {
        $speedKmh = self::randomFloat(0.0, 120.0);
        $this->km += $speedKmh * $this->config->intervalS / 3600.0 * $this->config->speedFactor;

        $this->oilTemp = min($this->oilTemp + self::randomFloat(0.5, 2.0), self::randomFloat(88.0, 95.0));

        $this->latitude = max(-90.0, min(90.0, $this->latitude + self::randomFloat(-0.0005, 0.0005)));
        $this->longitude = max(-180.0, min(180.0, $this->longitude + self::randomFloat(-0.0005, 0.0005)));

        return [
            'deviceId' => $this->config->deviceId,
            'readAt' => self::isoUtc($now),
            'km' => (int) $this->km,
            'oilTemp' => round($this->oilTemp, 1),
            'gps' => [
                'latitude' => round($this->latitude, 6),
                'longitude' => round($this->longitude, 6),
            ],
        ];
    }

    public static function isoUtc(int $timestamp): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    private static function randomFloat(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }
}

/**
 * Converts a valid reading into the message to publish, depending on MODE.
 *
 * - normal:      payload exactly as in the contract.
 * - invalid-km:  cycles through negative km (V3), non-integer km (V3) and an absurd jump (V5).
 * - bad-payload: cycles through truncated JSON, deviceId different from the topic (V1), readAt in the future and
 *                too old (V2), oilTemp/latitude out of bounds (V3).
 */
final class PayloadBuilder
{
    public const INVALID_KM_CASES = 3;
    public const BAD_PAYLOAD_CASES = 5;

    /**
     * @param array{deviceId: string, readAt: string, km: int, oilTemp: float, gps: array{latitude: float, longitude: float}} $reading
     */
    public static function build(Config $config, array $reading, int $sequence, int $now): string
    {
        return match ($config->mode) {
            Config::MODE_INVALID_KM => self::encode(self::withInvalidKm($reading, $sequence)),
            Config::MODE_BAD_PAYLOAD => self::badPayload($config, $reading, $sequence, $now),
            default => self::encode($reading),
        };
    }

    /**
     * @param array<string, mixed> $reading
     *
     * @return array<string, mixed>
     */
    private static function withInvalidKm(array $reading, int $sequence): array
    {
        $reading['km'] = match ($sequence % self::INVALID_KM_CASES) {
            0 => -1,
            1 => $reading['km'] + 0.5,
            default => $reading['km'] + 1_000_000,
        };

        return $reading;
    }

    /**
     * @param array<string, mixed> $reading
     */
    private static function badPayload(Config $config, array $reading, int $sequence, int $now): string
    {
        switch ($sequence % self::BAD_PAYLOAD_CASES) {
            case 0:
                return sprintf('{"deviceId":"%s","readAt":', $config->deviceId);
            case 1:
                $reading['deviceId'] = $config->deviceId . '-X';
                break;
            case 2:
                $reading['readAt'] = TelemetryGenerator::isoUtc($now + 3600);
                break;
            case 3:
                $reading['readAt'] = TelemetryGenerator::isoUtc($now - 2 * 86400);
                break;
            default:
                $reading['oilTemp'] = 999.9;
                $reading['gps'] = ['latitude' => 123.456789, 'longitude' => -25.671];
                break;
        }

        return self::encode($reading);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}

/**
 * Main loop: connects to the broker, publishes every INTERVAL_S and keeps the connection alive between readings.
 */
final class Simulator
{
    private bool $running = true;
    private bool $failed = false;
    private int $sequence = 0;

    public function __construct(private readonly Config $config)
    {
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(): int
    {
        $generator = new TelemetryGenerator($this->config);
        $client = new MqttClient(
            $this->config->host,
            $this->config->port,
            $this->config->deviceId,
            MqttClient::MQTT_3_1_1,
        );

        $settings = (new ConnectionSettings())
            ->setUsername($this->config->username)
            ->setPassword($this->config->password === '' ? null : $this->config->password)
            ->setKeepAliveInterval(30)
            ->setConnectTimeout(10)
            ->setSocketTimeout(5)
            ->setUseTls($this->config->useTls)
            ->setTlsVerifyPeer(true)
            ->setTlsVerifyPeerName(true)
            ->setTlsSelfSignedAllowed(false)
            ->setTlsCertificateAuthorityFile($this->config->tlsCaFile)
            ->setReconnectAutomatically(true)
            ->setMaxReconnectAttempts(5)
            ->setDelayBetweenReconnectAttempts(2000);

        $this->log(sprintf(
            'Starting: deviceId=%s broker=%s:%d tls=%s user=%s mode=%s startKm=%d interval=%ss factor=%s',
            $this->config->deviceId,
            $this->config->host,
            $this->config->port,
            $this->config->useTls ? 'yes' : 'no',
            $this->config->username,
            $this->config->mode,
            $this->config->startKm,
            self::formatNumber($this->config->intervalS),
            self::formatNumber($this->config->speedFactor),
        ));

        if (!$this->connect($client, $settings)) {
            return $this->failed ? 1 : 0;
        }

        $topic = $this->config->topic();
        $loopStartedAt = microtime(true);
        $nextTickAt = microtime(true);

        while ($this->running) {
            $now = time();
            $reading = $generator->next($now);
            $payload = PayloadBuilder::build($this->config, $reading, $this->sequence, $now);
            $this->sequence++;

            try {
                $client->publish($topic, $payload, MqttClient::QOS_AT_LEAST_ONCE, false);
                $this->log(sprintf('[%s] %s', $topic, $payload));
            } catch (MqttClientException $e) {
                $this->log('Failed to publish: ' . $e->getMessage(), true);
                if (!$this->reconnect($client, $settings)) {
                    break;
                }
            }

            // Between readings, keep processing traffic (PUBACK, ping, QoS 1 resends).
            $nextTickAt += $this->config->intervalS;
            try {
                while ($this->running && microtime(true) < $nextTickAt) {
                    $client->loopOnce($loopStartedAt, true, 100000);
                }
            } catch (MqttClientException $e) {
                $this->log('Connection failure: ' . $e->getMessage(), true);
                if (!$this->reconnect($client, $settings)) {
                    break;
                }
            }
        }

        $this->disconnect($client);
        $this->log('Simulator stopped.');

        return $this->failed ? 1 : 0;
    }

    private function connect(MqttClient $client, ConnectionSettings $settings): bool
    {
        $attempt = 0;

        while ($this->running) {
            $attempt++;
            try {
                // Persistent session: the only one compatible with the library's automatic reconnection.
                $client->connect($settings, false);
                $this->log('Connected to the broker.');

                return true;
            } catch (ConnectingToBrokerFailedException $e) {
                $this->log(sprintf('Connection %d failed: %s', $attempt, $e->getMessage()), true);

                $fatal = [
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_INVALID_CREDENTIALS,
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_UNAUTHORIZED,
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_IDENTIFIER_REJECTED,
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_PROTOCOL_VERSION,
                ];
                if (in_array($e->getCode(), $fatal, true)) {
                    $this->log('Authentication/authorization or protocol error: not worth retrying.', true);
                    $this->failed = true;
                    $this->running = false;

                    return false;
                }
            } catch (MqttClientException $e) {
                $this->log(sprintf('Connection %d failed: %s', $attempt, $e->getMessage()), true);
            }

            $this->sleepInterruptible(min(30.0, 2.0 ** min($attempt, 5)));
        }

        return false;
    }

    private function reconnect(MqttClient $client, ConnectionSettings $settings): bool
    {
        $this->log('Trying to reconnect to the broker.', true);

        return $this->connect($client, $settings);
    }

    private function disconnect(MqttClient $client): void
    {
        try {
            if ($client->isConnected()) {
                $client->disconnect();
            }
        } catch (MqttClientException $e) {
            $this->log('Error while disconnecting: ' . $e->getMessage(), true);
        }
    }

    private function sleepInterruptible(float $seconds): void
    {
        $until = microtime(true) + $seconds;
        while ($this->running && microtime(true) < $until) {
            usleep(100000);
        }
    }

    private function log(string $message, bool $isError = false): void
    {
        $line = sprintf("%s %s\n", gmdate('Y-m-d\TH:i:s\Z'), $message);
        if ($isError) {
            fwrite(STDERR, $line);

            return;
        }
        fwrite(STDOUT, $line);
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}

/**
 * Entry point (only runs when the file is executed directly: `php simulator.php`).
 */
function main(): int
{
    try {
        $config = Config::fromEnv(getenv());
    } catch (ConfigException $e) {
        fwrite(STDERR, 'Invalid configuration: ' . $e->getMessage() . "\n");

        return 78;
    }

    $simulator = new Simulator($config);

    // Ctrl+C / docker stop: terminates with a clean DISCONNECT.
    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static fn () => $simulator->stop());
        pcntl_signal(SIGTERM, static fn () => $simulator->stop());
    }

    return $simulator->run();
}

if (PHP_SAPI === 'cli' && isset($_SERVER['argv'][0]) && realpath($_SERVER['argv'][0]) === __FILE__) {
    exit(main());
}