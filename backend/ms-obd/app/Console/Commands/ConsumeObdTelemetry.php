<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Clients\MqttConnectionFactory;
use App\Services\ObdTelemetryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * Daemon que subscreve obd/telemetry/+ no RabbitMQ (sessão persistente, QoS 1) e entrega cada
 * leitura ao ObdTelemetryService. Nenhuma mensagem nem falha de HTTP termina o processo.
 */
final class ConsumeObdTelemetry extends Command
{
    protected $signature = 'obd:consume';

    protected $description = 'Subscreve obd/telemetry/+ e processa as leituras do dispositivo OBD';

    private bool $stopping = false;

    private ?MqttClient $client = null;

    public function handle(ObdTelemetryService $service): int
    {
        $this->registerSignalHandlers();

        $delay = max(1, (int) config('mqtt.reconnect_delay_seconds', 5));
        $filter = (string) config('mqtt.topics.telemetry_filter', 'obd/telemetry/+');

        while (! $this->stopping) {
            $this->client = MqttConnectionFactory::client((string) config('mqtt.client_id'));

            try {
                // cleanSession = false: o broker guarda as mensagens enquanto o serviço estiver em baixo.
                $this->client->connect(MqttConnectionFactory::settings(), false);

                $this->client->subscribe(
                    $filter,
                    function (string $topic, string $message) use ($service): void {
                        try {
                            $service->handle($topic, $message);
                        } catch (Throwable $e) {
                            Log::error(
                                sprintf('Erro inesperado ao tratar uma mensagem MQTT: %s', $e->getMessage()),
                                ['topic' => $topic, 'exception' => $e::class],
                            );
                        }
                    },
                    MqttClient::QOS_AT_LEAST_ONCE,
                );

                $this->logInfo(sprintf('Ligado ao broker %s:%d; subscrito a %s.', config('mqtt.host'), config('mqtt.port'), $filter));

                $this->client->loop(true);
            } catch (Throwable $e) {
                Log::error(
                    sprintf('Ligação MQTT perdida ou indisponível: %s', $e->getMessage()),
                    ['exception' => $e::class],
                );
            } finally {
                $this->disconnectQuietly();
            }

            if ($this->stopping) {
                break;
            }

            $this->logInfo(sprintf('A tentar nova ligação ao broker dentro de %d s.', $delay));
            $this->waitBeforeReconnect($delay);
        }

        $this->logInfo('Consumidor OBD terminado.');

        return self::SUCCESS;
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_async_signals') || ! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function (): void {
            $this->stopping = true;
            $this->client?->interrupt();
        };

        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);
    }

    private function waitBeforeReconnect(int $seconds): void
    {
        $ticks = $seconds * 10;

        for ($i = 0; $i < $ticks && ! $this->stopping; $i++) {
            usleep(100000);
        }
    }

    private function disconnectQuietly(): void
    {
        $client = $this->client;
        $this->client = null;

        if ($client === null) {
            return;
        }

        try {
            if ($client->isConnected()) {
                $client->disconnect();
            }
        } catch (Throwable) {
            // A ligação já tinha caído; nada a fazer.
        }
    }

    private function logInfo(string $message): void
    {
        $this->info($message);
        Log::info($message);
    }
}
