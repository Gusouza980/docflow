<?php

namespace App\Console\Commands\Finance;

use App\Actions\Finance\GenerateReceivableRecurrences;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class GenerateRecurringReceivablesCommand extends Command
{
    protected $signature = 'finance:generate-recurring-receivables';

    protected $description = 'Gera cobranças recorrentes pendentes de forma idempotente';

    public function handle(
        GenerateReceivableRecurrences $generateReceivableRecurrences,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, function () use ($generateReceivableRecurrences): array {
            $generated = $generateReceivableRecurrences->execute();

            return ['generated' => count($generated)];
        });

        $this->info(sprintf('Cobranças geradas: %d', $meta['generated'] ?? 0));

        return self::SUCCESS;
    }
}
