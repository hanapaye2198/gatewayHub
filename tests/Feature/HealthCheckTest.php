<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertContent('ok');
    }
}
