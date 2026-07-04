<?php

namespace App\Console\Commands\Billing;

use App\Actions\Billing\ExpireTrials;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class ExpireTrialsCommand extends Command
{
    protected $signature = 'subscriptions:expire-trials';

    protected $description = 'Cancela trials expirados e suspende organizações afetadas';

    public function handle(
        ExpireTrials $expireTrials,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, fn (): array => $expireTrials->execute());

        $this->info(sprintf('Trials expirados: %d', $meta['expired'] ?? 0));

        return self::SUCCESS;
    }
}
