<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\MqttPublisherInterface;
use App\Services\ObdTelemetryService;
use App\Services\TelemetryOutcome;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeMqttPublisher;
use Tests\TestCase;

final class ObdTelemetryServiceTest extends TestCase
{
    private const BASE_URL = 'http://sapi-vehicles.test';

    private const DEVICE_ID = 'OBD-7F3A-0001';

    private const TOPIC = 'obd/telemetry/OBD-7F3A-0001';

    private const VEHICLE_ID = '3f6c1a52-8a7e-4c2b-9d61-0b8f2e7a4c11';

    private const USER_ID = '9b2d7e14-1c3a-4f5e-8a90-6d4c2b1e7f03';

    /** "Agora" fixo dos testes. */
    private const NOW = '2026-11-25T10:00:00Z';

    private FakeMqttPublisher $publisher;

    private ObdTelemetryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::NOW));

        config([
            'services.sapi_vehicles.url' => self::BASE_URL,
            'mqtt.max_speed_kmh' => 250.0,
        ]);

        $this->publisher = new FakeMqttPublisher();
        $this->app->instance(MqttPublisherInterface::class, $this->publisher);

        Log::spy();

        $this->service = $this->app->make(ObdTelemetryService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------------------------------------
    // Fluxo feliz
    // ---------------------------------------------------------------------------------------------

    public function test_reading_that_raises_km_is_stored_updates_mileage_and_publishes_vehicle_state(): void
    {
        $this->fakeSapi();

        $outcome = $this->service->handle(self::TOPIC, self::payload());

        $this->assertSame(TelemetryOutcome::Published, $outcome);

        // Ordem exata: V4, V5, guardar leitura, km atual do veículo, atualizar km.
        $this->assertSame([
            'GET /obd-devices/' . self::DEVICE_ID,
            'GET /vehicles/' . self::VEHICLE_ID . '/readings/latest',
            'POST /vehicles/' . self::VEHICLE_ID . '/readings',
            'GET /vehicles/' . self::VEHICLE_ID,
            'PUT /vehicles/' . self::VEHICLE_ID . '/mileage',
        ], $this->requests());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/readings')
            && $request->data() == [
                'deviceId' => self::DEVICE_ID,
                'readAt' => '2026-11-25T09:59:55Z',
                'km' => 9100,
                'oilTemp' => 89.5,
                'gps' => ['latitude' => 37.7417, 'longitude' => -25.671],
            ]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/mileage')
            && $request->data() == ['km' => 9100]);

        $this->assertCount(1, $this->publisher->published);
        $this->assertSame('app/vehicle/state', $this->publisher->published[0]['topic']);
        $this->assertSame(1, $this->publisher->published[0]['qos']);
        $this->assertFalse($this->publisher->published[0]['retain']);
        $this->assertSame([
            'vehicleId' => self::VEHICLE_ID,
            'userId' => self::USER_ID,
            'km' => 9100,
            'previousKm' => 9000,
            'nextAlertKm' => 10000,
            'readAt' => '2026-11-25T09:59:55Z',
        ], $this->publisher->published[0]['payload']);
    }

    public function test_state_keeps_next_alert_km_as_null(): void
    {
        $this->fakeSapi([
            'PUT /vehicles/' . self::VEHICLE_ID . '/mileage' => [200, self::mileageBody(9100, 9000, null)],
        ]);

        $this->assertSame(TelemetryOutcome::Published, $this->service->handle(self::TOPIC, self::payload()));

        $this->assertArrayHasKey('nextAlertKm', $this->publisher->published[0]['payload']);
        $this->assertNull($this->publisher->published[0]['payload']['nextAlertKm']);
    }

    public function test_read_at_with_offset_is_normalised_to_utc(): void
    {
        $this->fakeSapi();

        $outcome = $this->service->handle(self::TOPIC, self::payload(['readAt' => '2026-11-25T10:59:55+01:00']));

        $this->assertSame(TelemetryOutcome::Published, $outcome);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && ($request->data()['readAt'] ?? null) === '2026-11-25T09:59:55Z');
        $this->assertSame('2026-11-25T09:59:55Z', $this->publisher->published[0]['payload']['readAt']);
    }

    public function test_extra_fields_are_ignored(): void
    {
        $this->fakeSapi();

        $outcome = $this->service->handle(self::TOPIC, self::payload(['firmware' => '1.2.3', 'rpm' => 3000]));

        $this->assertSame(TelemetryOutcome::Published, $outcome);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && ! array_key_exists('firmware', $request->data())
            && ! array_key_exists('rpm', $request->data()));
    }

    public function test_reading_with_null_or_absent_optional_fields_is_stored_without_touching_mileage(): void
    {
        $this->fakeSapi();

        $message = json_encode(['deviceId' => self::DEVICE_ID, 'readAt' => '2026-11-25T09:59:55Z'], JSON_THROW_ON_ERROR);

        $this->assertSame(TelemetryOutcome::Stored, $this->service->handle(self::TOPIC, $message));

        $this->assertSame([
            'GET /obd-devices/' . self::DEVICE_ID,
            'POST /vehicles/' . self::VEHICLE_ID . '/readings',
        ], $this->requests());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->data() == [
                'deviceId' => self::DEVICE_ID,
                'readAt' => '2026-11-25T09:59:55Z',
                'km' => null,
                'oilTemp' => null,
                'gps' => null,
            ]);
        $this->assertSame([], $this->publisher->published);
    }

    // ---------------------------------------------------------------------------------------------
    // V1: JSON válido e deviceId igual ao do tópico
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidV1Messages(): array
    {
        return [
            'JSON inválido' => ['{not json'],
            'string JSON' => ['"texto"'],
            'lista JSON' => ['[1, 2, 3]'],
            'deviceId diferente do tópico' => [self::payload(['deviceId' => 'OBD-OUTRO-0002'])],
            'deviceId ausente' => [json_encode(['readAt' => '2026-11-25T09:59:55Z', 'km' => 9100], JSON_THROW_ON_ERROR)],
            'deviceId não é texto' => [self::payload(['deviceId' => 7])],
        ];
    }

    #[DataProvider('invalidV1Messages')]
    public function test_v1_discards_invalid_json_or_device_id_mismatch(string $message): void
    {
        $this->assertDiscardedWithoutCallingSapi($message, 'V1');
    }

    public function test_v1_discards_messages_from_unexpected_topics(): void
    {
        $this->fakeSapi();

        foreach (['obd/telemetry/OBD-7F3A-0001/extra', 'obd/telemetry/', 'outro/topico/OBD-7F3A-0001'] as $topic) {
            $this->assertSame(TelemetryOutcome::Discarded, $this->service->handle($topic, self::payload()));
        }

        Http::assertNothingSent();
        $this->assertSame([], $this->publisher->published);
    }

    // ---------------------------------------------------------------------------------------------
    // V2: readAt ISO 8601, no máximo 1 min no futuro e 24 h no passado
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidV2Messages(): array
    {
        return [
            'readAt ausente' => [self::payload(['readAt' => null])],
            'readAt não é texto' => [self::payload(['readAt' => 1764064795])],
            'texto livre' => [self::payload(['readAt' => 'ontem'])],
            'data com espaço em vez de T' => [self::payload(['readAt' => '2026-11-25 09:59:55'])],
            'sem fuso horário' => [self::payload(['readAt' => '2026-11-25T09:59:55'])],
            'data impossível' => [self::payload(['readAt' => '2026-11-31T09:59:55Z'])],
            'mais de 1 minuto no futuro' => [self::payload(['readAt' => '2026-11-25T10:01:01Z'])],
            'mais de 24 horas no passado' => [self::payload(['readAt' => '2026-11-24T09:59:59Z'])],
        ];
    }

    #[DataProvider('invalidV2Messages')]
    public function test_v2_discards_invalid_or_out_of_window_read_at(string $message): void
    {
        $this->assertDiscardedWithoutCallingSapi($message, 'V2');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function acceptedReadAtValues(): array
    {
        return [
            'exatamente 1 minuto no futuro' => ['2026-11-25T10:01:00Z'],
            'exatamente 24 horas no passado' => ['2026-11-24T10:00:00Z'],
            'com milissegundos' => ['2026-11-25T09:59:55.250Z'],
        ];
    }

    #[DataProvider('acceptedReadAtValues')]
    public function test_v2_accepts_read_at_inside_the_window(string $readAt): void
    {
        $this->fakeSapi();

        $outcome = $this->service->handle(self::TOPIC, self::payload(['readAt' => $readAt]));

        $this->assertSame(TelemetryOutcome::Published, $outcome);
    }

    // ---------------------------------------------------------------------------------------------
    // V3: km, oilTemp e gps
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidV3Messages(): array
    {
        return [
            'km negativo' => [self::payload(['km' => -1])],
            'km decimal' => [self::payload(['km' => 9100.5])],
            'km em texto' => [self::payload(['km' => '9100'])],
            'oilTemp acima de 200' => [self::payload(['oilTemp' => 200.1])],
            'oilTemp abaixo de -40' => [self::payload(['oilTemp' => -40.1])],
            'oilTemp em texto' => [self::payload(['oilTemp' => 'quente'])],
            'gps só com latitude' => [self::payload(['gps' => ['latitude' => 37.7417]])],
            'gps só com longitude' => [self::payload(['gps' => ['longitude' => -25.671]])],
            'gps com longitude nula' => [self::payload(['gps' => ['latitude' => 37.7417, 'longitude' => null]])],
            'gps vazio' => [self::payload(['gps' => []])],
            'gps em texto' => [self::payload(['gps' => 'aqui'])],
            'latitude acima de 90' => [self::payload(['gps' => ['latitude' => 90.1, 'longitude' => 0]])],
            'latitude abaixo de -90' => [self::payload(['gps' => ['latitude' => -90.1, 'longitude' => 0]])],
            'longitude acima de 180' => [self::payload(['gps' => ['latitude' => 0, 'longitude' => 180.1]])],
            'longitude abaixo de -180' => [self::payload(['gps' => ['latitude' => 0, 'longitude' => -180.1]])],
        ];
    }

    #[DataProvider('invalidV3Messages')]
    public function test_v3_discards_out_of_range_or_incomplete_values(string $message): void
    {
        $this->assertDiscardedWithoutCallingSapi($message, 'V3');
    }

    public function test_v3_accepts_values_on_the_limits(): void
    {
        $this->fakeSapi();

        $outcome = $this->service->handle(self::TOPIC, self::payload([
            'km' => 9100,
            'oilTemp' => -40,
            'gps' => ['latitude' => 90, 'longitude' => -180],
        ]));

        $this->assertSame(TelemetryOutcome::Published, $outcome);

        $this->fakeSapi();

        $outcome = $this->service->handle(self::TOPIC, self::payload([
            'oilTemp' => 200,
            'gps' => ['latitude' => -90, 'longitude' => 180],
        ]));

        $this->assertSame(TelemetryOutcome::Published, $outcome);
    }

    // ---------------------------------------------------------------------------------------------
    // V4: dispositivo emparelhado
    // ---------------------------------------------------------------------------------------------

    public function test_v4_discards_reading_from_unpaired_device_and_logs_info(): void
    {
        $this->fakeSapi([
            'GET /obd-devices/' . self::DEVICE_ID => [404, self::problemBody(404)],
        ]);

        $outcome = $this->service->handle(self::TOPIC, self::payload());

        $this->assertSame(TelemetryOutcome::Discarded, $outcome);
        $this->assertSame(['GET /obd-devices/' . self::DEVICE_ID], $this->requests());
        $this->assertSame([], $this->publisher->published);
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_contains($message, '(V4)'))->once();
        Log::shouldNotHaveReceived('error');
    }

    // ---------------------------------------------------------------------------------------------
    // V5: salto de km plausível (250 km/h por defeito)
    // ---------------------------------------------------------------------------------------------

    public function test_v5_discards_implausible_km_jump_and_logs_warning(): void
    {
        // 5 minutos desde a leitura anterior: no máximo 250 * 300 / 3600 = 20,83 km. Aqui seriam +50 km.
        $this->fakeSapi([
            'GET /vehicles/' . self::VEHICLE_ID . '/readings/latest' => [200, self::latestReadingBody(9100, '2026-11-25T09:55:00Z')],
        ]);

        $outcome = $this->service->handle(self::TOPIC, self::payload(['km' => 9150, 'readAt' => self::NOW]));

        $this->assertSame(TelemetryOutcome::Discarded, $outcome);
        $this->assertSame([
            'GET /obd-devices/' . self::DEVICE_ID,
            'GET /vehicles/' . self::VEHICLE_ID . '/readings/latest',
        ], $this->requests());
        $this->assertSame([], $this->publisher->published);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, '(V5)'))->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_v5_accepts_plausible_km_jump(): void
    {
        // +10 km em 5 minutos (máximo 20,83 km).
        $this->fakeSapi([
            'GET /vehicles/' . self::VEHICLE_ID . '/readings/latest' => [200, self::latestReadingBody(9100, '2026-11-25T09:55:00Z')],
            'PUT /vehicles/' . self::VEHICLE_ID . '/mileage' => [200, self::mileageBody(9110, 9100, 10000)],
        ]);

        $outcome = $this->service->handle(self::TOPIC, self::payload(['km' => 9110, 'readAt' => self::NOW]));

        $this->assertSame(TelemetryOutcome::Published, $outcome);
        $this->assertSame(9110, $this->publisher->published[0]['payload']['km']);
    }

    public function test_v5_limit_comes_from_max_speed_kmh_configuration(): void
    {
        config(['mqtt.max_speed_kmh' => 100000.0]);

        $this->fakeSapi([
            'GET /vehicles/' . self::VEHICLE_ID . '/readings/latest' => [200, self::latestReadingBody(9100, '2026-11-25T09:55:00Z')],
            'PUT /vehicles/' . self::VEHICLE_ID . '/mileage' => [200, self::mileageBody(9150, 9100, null)],
        ]);

        $outcome = $this->service->handle(self::TOPIC, self::payload(['km' => 9150, 'readAt' => self::NOW]));

        $this->assertSame(TelemetryOutcome::Published, $outcome);
    }

    public function test_v5_is_skipped_when_there_is_no_previous_reading_with_km(): void
    {
        // Resposta por defeito de /readings/latest: tudo nulo.
        $this->fakeSapi();

        $this->assertSame(TelemetryOutcome::Published, $this->service->handle(self::TOPIC, self::payload(['km' => 90000])));
    }

    // ---------------------------------------------------------------------------------------------
    // km igual ou inferior: guarda a leitura, mas não atualiza a km nem publica estado
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array<string, array{0: int}>
     */
    public static function nonIncreasingKm(): array
    {
        return [
            'km igual à do veículo' => [9100],
            'km inferior à do veículo' => [9050],
        ];
    }

    #[DataProvider('nonIncreasingKm')]
    public function test_equal_or_lower_km_stores_the_reading_but_neither_updates_mileage_nor_publishes_state(int $km): void
    {
        $this->fakeSapi([
            'GET /vehicles/' . self::VEHICLE_ID => [200, self::vehicleBody(9100)],
        ]);

        $outcome = $this->service->handle(self::TOPIC, self::payload(['km' => $km]));

        $this->assertSame(TelemetryOutcome::Stored, $outcome);
        $this->assertSame([
            'GET /obd-devices/' . self::DEVICE_ID,
            'GET /vehicles/' . self::VEHICLE_ID . '/readings/latest',
            'POST /vehicles/' . self::VEHICLE_ID . '/readings',
            'GET /vehicles/' . self::VEHICLE_ID,
        ], $this->requests());
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
        $this->assertSame([], $this->publisher->published);
        Log::shouldNotHaveReceived('error');
    }

    public function test_conflict_409_on_mileage_does_not_fail_nor_publish_state(): void
    {
        // A km do veículo mudou entre o GET e o PUT (ou a mensagem QoS 1 foi repetida).
        $this->fakeSapi([
            'PUT /vehicles/' . self::VEHICLE_ID . '/mileage' => [409, self::problemBody(409)],
        ]);

        $outcome = $this->service->handle(self::TOPIC, self::payload());

        $this->assertSame(TelemetryOutcome::Stored, $outcome);
        Http::assertSentCount(5);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT');
        $this->assertSame([], $this->publisher->published);
        Log::shouldNotHaveReceived('error');
    }

    // ---------------------------------------------------------------------------------------------
    // Falhas da SAPI ou do broker: o processamento não rebenta
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array<string, array{0: array<string, array{0: int, 1: array<string, mixed>|null}>}>
     */
    public static function failingSapiRoutes(): array
    {
        $vehicle = self::VEHICLE_ID;

        return [
            'GET /obd-devices com 500' => [['GET /obd-devices/' . self::DEVICE_ID => [500, self::problemBody(500)]]],
            'GET /obd-devices com resposta inválida' => [['GET /obd-devices/' . self::DEVICE_ID => [200, ['foo' => 'bar']]]],
            'GET /readings/latest com 500' => [["GET /vehicles/{$vehicle}/readings/latest" => [500, self::problemBody(500)]]],
            'POST /readings com 500' => [["POST /vehicles/{$vehicle}/readings" => [500, self::problemBody(500)]]],
            'POST /readings com 400' => [["POST /vehicles/{$vehicle}/readings" => [400, self::problemBody(400)]]],
            'GET /vehicles/{id} com 500' => [["GET /vehicles/{$vehicle}" => [500, self::problemBody(500)]]],
            'PUT /mileage com 500' => [["PUT /vehicles/{$vehicle}/mileage" => [500, self::problemBody(500)]]],
            'PUT /mileage com resposta inválida' => [["PUT /vehicles/{$vehicle}/mileage" => [200, ['km' => 9100]]]],
        ];
    }

    /**
     * @param array<string, array{0: int, 1: array<string, mixed>|null}> $routes
     */
    #[DataProvider('failingSapiRoutes')]
    public function test_sapi_errors_are_logged_without_crashing_and_publish_nothing(array $routes): void
    {
        $this->fakeSapi($routes);

        $outcome = $this->service->handle(self::TOPIC, self::payload());

        $this->assertSame(TelemetryOutcome::Failed, $outcome);
        $this->assertSame([], $this->publisher->published);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_server_error_on_save_reading_stops_before_updating_mileage(): void
    {
        $this->fakeSapi([
            'POST /vehicles/' . self::VEHICLE_ID . '/readings' => [500, self::problemBody(500)],
        ]);

        $this->assertSame(TelemetryOutcome::Failed, $this->service->handle(self::TOPIC, self::payload()));

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
    }

    public function test_connection_failure_to_sapi_does_not_crash(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        $outcome = $this->service->handle(self::TOPIC, self::payload());

        $this->assertSame(TelemetryOutcome::Failed, $outcome);
        $this->assertSame([], $this->publisher->published);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_broker_failure_while_publishing_state_does_not_crash(): void
    {
        $this->fakeSapi();
        $this->publisher->failing = true;

        $outcome = $this->service->handle(self::TOPIC, self::payload());

        $this->assertSame(TelemetryOutcome::Failed, $outcome);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT');
        Log::shouldHaveReceived('error')->once();
    }

    // ---------------------------------------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     */
    private static function payload(array $overrides = []): string
    {
        return json_encode(array_merge([
            'deviceId' => self::DEVICE_ID,
            'readAt' => '2026-11-25T09:59:55Z',
            'km' => 9100,
            'oilTemp' => 89.5,
            'gps' => ['latitude' => 37.7417, 'longitude' => -25.671],
        ], $overrides), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private static function vehicleBody(int $km): array
    {
        return ['id' => self::VEHICLE_ID, 'userId' => self::USER_ID, 'km' => $km];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mileageBody(int $km, int $previousKm, ?int $nextAlertKm): array
    {
        return ['vehicleId' => self::VEHICLE_ID, 'km' => $km, 'previousKm' => $previousKm, 'nextAlertKm' => $nextAlertKm];
    }

    /**
     * @return array<string, mixed>
     */
    private static function latestReadingBody(?int $km, ?string $readAt): array
    {
        return [
            'vehicleId' => self::VEHICLE_ID,
            'deviceId' => self::DEVICE_ID,
            'readAt' => $readAt,
            'km' => $km,
            'oilTemp' => null,
            'gps' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function problemBody(int $status): array
    {
        return ['title' => 'Erro simulado', 'status' => $status];
    }

    /**
     * Simula a SAPI Veículos. Cada rota é "MÉTODO /caminho" => [estado HTTP, corpo JSON].
     * Pedidos sem rota definida respondem 500, para o teste falhar de forma visível.
     *
     * @param array<string, array{0: int, 1: array<string, mixed>|null}> $overrides
     */
    private function fakeSapi(array $overrides = []): void
    {
        $vehicle = '/vehicles/' . self::VEHICLE_ID;

        $routes = array_merge([
            'GET /obd-devices/' . self::DEVICE_ID => [200, [
                'deviceId' => self::DEVICE_ID,
                'vehicleId' => self::VEHICLE_ID,
                'userId' => self::USER_ID,
            ]],
            "GET {$vehicle}/readings/latest" => [200, self::latestReadingBody(null, null)],
            "POST {$vehicle}/readings" => [201, self::latestReadingBody(9100, '2026-11-25T09:59:55Z')],
            "GET {$vehicle}" => [200, self::vehicleBody(9000)],
            "PUT {$vehicle}/mileage" => [200, self::mileageBody(9100, 9000, 10000)],
        ], $overrides);

        Http::fake(function (Request $request) use ($routes) {
            $route = $request->method() . ' ' . parse_url($request->url(), PHP_URL_PATH);

            if (! isset($routes[$route])) {
                return Http::response(['title' => 'Pedido inesperado: ' . $route, 'status' => 500], 500);
            }

            [$status, $body] = $routes[$route];

            return Http::response($body, $status);
        });
    }

    /**
     * @return array<int, string> pedidos feitos à SAPI, pela ordem, como "MÉTODO /caminho"
     */
    private function requests(): array
    {
        return Http::recorded()
            ->map(fn (array $pair): string => $pair[0]->method() . ' ' . parse_url($pair[0]->url(), PHP_URL_PATH))
            ->values()
            ->all();
    }

    private function assertDiscardedWithoutCallingSapi(string $message, string $rule): void
    {
        $this->fakeSapi();

        $outcome = $this->service->handle(self::TOPIC, $message);

        $this->assertSame(TelemetryOutcome::Discarded, $outcome);
        Http::assertNothingSent();
        $this->assertSame([], $this->publisher->published);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $logged): bool => str_contains($logged, '(' . $rule . ')'))
            ->once();
        Log::shouldNotHaveReceived('error');
    }
}
