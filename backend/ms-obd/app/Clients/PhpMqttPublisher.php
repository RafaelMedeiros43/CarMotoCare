<?php

declare(strict_types=1);

namespace App\Clients;

use App\Contracts\MqttPublisherInterface;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * Publicador MQTT com ligação própria e preguiçosa (não partilha o cliente do subscritor, para não
 * bloquear o ciclo de leitura nem colidir com o client id da sessão persistente).
 */
final class PhpMqttPublisher implements MqttPublisherInterface
{
    private ?MqttClient $client = null;

    public function publish(string $topic, string $payload, int $qos = 1, bool $retain = false): void
    {
        try {
            $this->send($topic, $payload, $qos, $retain);
        } catch (Throwable) {
            // Ligação perdida (ex.: o broker reiniciou): refaz a ligação e tenta mais uma vez.
            $this->disconnect();
            $this->send($topic, $payload, $qos, $retain);
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function send(string $topic, string $payload, int $qos, bool $retain): void
    {
        $client = $this->connection();
        $client->publish($topic, $payload, $qos, $retain);

        if ($qos > 0) {
            // Processa o PUBACK (e eventuais reenvios) até a fila de mensagens por confirmar esvaziar.
            $client->loop(true, true, 5);
        }
    }

    private function connection(): MqttClient
    {
        if ($this->client !== null && $this->client->isConnected()) {
            return $this->client;
        }

        $client = MqttConnectionFactory::client(config('mqtt.client_id') . '-publisher');
        $client->connect(MqttConnectionFactory::settings(), true);

        return $this->client = $client;
    }

    private function disconnect(): void
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
            // Já estava desligado; nada a fazer.
        }
    }
}
