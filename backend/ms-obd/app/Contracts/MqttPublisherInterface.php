<?php

declare(strict_types=1);

namespace App\Contracts;

interface MqttPublisherInterface
{
    /**
     * Publica uma mensagem no broker MQTT.
     *
     * @throws \Throwable se a mensagem não puder ser publicada.
     */
    public function publish(string $topic, string $payload, int $qos = 1, bool $retain = false): void;
}
