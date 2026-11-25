<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\MqttPublisherInterface;
use RuntimeException;

final class FakeMqttPublisher implements MqttPublisherInterface
{
    /**
     * @var array<int, array{topic: string, payload: array<string, mixed>, qos: int, retain: bool}>
     */
    public array $published = [];

    public bool $failing = false;

    public function publish(string $topic, string $payload, int $qos = 1, bool $retain = false): void
    {
        if ($this->failing) {
            throw new RuntimeException('Broker indisponível (fake).');
        }

        $this->published[] = [
            'topic' => $topic,
            'payload' => json_decode($payload, true, 16, JSON_THROW_ON_ERROR),
            'qos' => $qos,
            'retain' => $retain,
        ];
    }
}
