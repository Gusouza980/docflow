<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DocflowHealthCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_health_passes_in_local_environment(): void
    {
        config([
            'app.env' => 'local',
            'mail.default' => 'smtp',
            'queue.default' => 'database',
            'mail.from.address' => 'noreply@docflow.test',
        ]);

        $this->artisan('docflow:health')->assertSuccessful();
    }

    public function test_health_fails_when_production_uses_log_mailer(): void
    {
        config([
            'app.env' => 'production',
            'mail.default' => 'log',
            'queue.default' => 'database',
            'mail.from.address' => 'noreply@docflow.test',
        ]);

        $this->artisan('docflow:health')->assertFailed();
    }

    public function test_health_fails_when_production_uses_sync_queue(): void
    {
        config([
            'app.env' => 'production',
            'mail.default' => 'smtp',
            'queue.default' => 'sync',
            'mail.from.address' => 'noreply@docflow.test',
        ]);

        $this->artisan('docflow:health')->assertFailed();
    }
}
