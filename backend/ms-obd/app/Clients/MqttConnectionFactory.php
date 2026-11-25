<?php

declare(strict_types=1);

namespace App\Clients;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

/**
 * Cria clientes e definições de ligação MQTT a partir de config/mqtt.php.
 * Partilhado pelo comando obd:consume (subscritor) e pelo PhpMqttPublisher (publicador).
 */
final class MqttConnectionFactory
{
    public static function client(string $clientId): MqttClient
    {
        return new MqttClient(
            (string) config('mqtt.host'),
            (int) config('mqtt.port'),
            $clientId,
            MqttClient::MQTT_3_1_1,
        );
    }

    public static function settings(): ConnectionSettings
    {
        $username = config('mqtt.username');
        $password = config('mqtt.password');

        return (new ConnectionSettings())
            ->setUsername(is_string($username) && $username !== '' ? $username : null)
            ->setPassword(is_string($password) && $password !== '' ? $password : null)
            ->setKeepAliveInterval(30)
            ->setConnectTimeout(10)
            ->setSocketTimeout(5)
            // As reconexões são feitas pelo ciclo do comando obd:consume.
            ->setReconnectAutomatically(false);
    }
}
