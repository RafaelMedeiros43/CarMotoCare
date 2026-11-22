<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_returns_status_up(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'UP']);
    }

    public function test_legacy_up_route_is_not_exposed(): void
    {
        $this->getJson('/up')->assertNotFound();
    }
}
