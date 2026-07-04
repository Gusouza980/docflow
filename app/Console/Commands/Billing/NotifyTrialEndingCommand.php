<?php

namespace App\Console\Commands\Billing;

use App\Actions\Billing\NotifyTrialEndingSubscriptions;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class NotifyTrialEndingCommand extends Command
{
    protected $signature = 'billing:notify-trial-ending';

    protected $description = 'Notifica admins sobre trials expirando em 3 e 1 dias';

    public function handle(
        NotifyTrialEndingSubscriptions $notifyTrialEndingSubscriptions,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, fn (): array => $notifyTrialEndingSubscriptions->execute());

        $this->info(sprintf('Notificações de trial enviadas: %d', $meta['notified'] ?? 0));

        return self::SUCCESS;
    }
}
