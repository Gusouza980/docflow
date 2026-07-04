<?php

namespace App\Support;

use App\Models\SchedulerRunLog;
use Throwable;

class SchedulerRunLogger
{
    /**
     * @param  callable(): array<string, mixed>|null  $callback
     * @return array<string, mixed>|null
     */
    public function run(string $command, callable $callback): ?array
    {
        $startedAt = now();
        $started = microtime(true);
        $meta = [];
        $result = 'success';

        try {
            $meta = $callback() ?? [];
        } catch (Throwable $exception) {
            $result = 'failed';
            $meta = ['error' => $exception->getMessage()];

            throw $exception;
        } finally {
            SchedulerRunLog::create([
                'command' => $command,
                'ran_at' => $startedAt,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'result' => $result,
                'meta' => $meta,
            ]);
        }

        return $meta;
    }
}
