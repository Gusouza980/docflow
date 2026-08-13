<?php

namespace Tests\Unit;

use App\Automations\EstimatesAutomationMinutesSaved;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use Tests\TestCase;

class EstimatesAutomationMinutesSavedTest extends TestCase
{
    public function test_it_sums_configured_minutes_per_action_type(): void
    {
        $minutes = app(EstimatesAutomationMinutesSaved::class)->forActions([
            ['type' => AutomationRule::ACTION_CREATE_TASKS_FROM_TEMPLATE],
            ['type' => AutomationRule::ACTION_NOTIFY_ORGANIZATION_MEMBERS],
            ['type' => AutomationRule::ACTION_CREATE_DOCUMENT_REQUEST],
        ]);

        $this->assertSame(8 + 3 + 6, $minutes);
    }

    public function test_it_uses_default_minutes_for_unknown_action_types(): void
    {
        $minutes = app(EstimatesAutomationMinutesSaved::class)->forActions([
            ['type' => 'unknown_action'],
        ]);

        $this->assertSame(5, $minutes);
    }

    public function test_failed_logs_do_not_count_as_saved_time(): void
    {
        $log = new AutomationLog([
            'status' => AutomationLog::STATUS_FAILED,
            'result' => ['error' => 'boom'],
        ]);

        $this->assertSame(0, app(EstimatesAutomationMinutesSaved::class)->forLog($log));
    }
}
