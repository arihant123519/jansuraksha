<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * This is an API-only app (no web routes registered); the framework health
     * check lives at /up.
     */
    public function test_the_health_endpoint_returns_a_successful_response(): void
    {
        $this->get('/up')->assertStatus(200);
    }
}
