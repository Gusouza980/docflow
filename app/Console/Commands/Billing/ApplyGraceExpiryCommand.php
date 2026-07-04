<?php

namespace App\Console\Commands\Billing;

use App\Actions\Billing\ApplyGraceExpiry;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class ApplyGraceExpiryCommand extends Command
{
    protected $signature = 'subscriptions:apply-grace-expiry';

    protected $description = 'Cancela assinaturas past_due após o período de tolerância e suspende a organização';

    public function handle(
        ApplyGraceExpiry $applyGraceExpiry,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, fn (): array => $applyGraceExpiry->execute());

        $this->info(sprintf('Assinaturas expiradas após grace: %d', $meta['expired'] ?? 0));

        return self::SUCCESS;
    }
}
