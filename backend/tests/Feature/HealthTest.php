<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_unified_response(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'code' => 0,
                'message' => 'ok',
                'data' => [
                    'status' => 'ok',
                    'version' => 'v1',
                ],
            ]);
    }
}
