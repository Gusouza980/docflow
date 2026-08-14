<?php

namespace App\Automations;

use App\Models\AutomationLog;

class EstimatesAutomationMinutesSaved
{
    /**
     * @param  list<array{type?: string|null}|string>  $actions
     */
    public function forActions(array $actions): int
    {
        $minutesMap = config('docflow.automation_roi.minutes_saved_per_action', []);
        $default = (int) ($minutesMap['default'] ?? 5);
        $total = 0;

        foreach ($actions as $action) {
            $type = is_string($action) ? $action : ($action['type'] ?? null);

            if (! is_string($type) || $type === '') {
                continue;
            }

            $total += (int) ($minutesMap[$type] ?? $default);
        }

        return $total;
    }

    public function forLog(AutomationLog $log): int
    {
        if ($log->status !== AutomationLog::STATUS_SUCCEEDED) {
            return 0;
        }

        $result = $log->result ?? [];

        if (! is_array($result) || $result === [] || array_key_exists('error', $result)) {
            return 0;
        }

        return $this->forActions($result);
    }
}
