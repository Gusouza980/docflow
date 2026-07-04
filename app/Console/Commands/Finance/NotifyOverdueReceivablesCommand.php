<?php

namespace App\Console\Commands\Finance;

use App\Actions\Finance\NotifyOverdueReceivableToClient;
use App\Models\Receivable;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class NotifyOverdueReceivablesCommand extends Command
{
    protected $signature = 'finance:notify-overdue-receivables';

    protected $description = 'Notifica clientes no portal sobre cobranças vencidas (com cooldown)';

    public function handle(
        NotifyOverdueReceivableToClient $notifyOverdueReceivableToClient,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, function () use ($notifyOverdueReceivableToClient): array {
            $notified = 0;
            $skipped = 0;

            Receivable::query()
                ->with('client')
                ->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])
                ->whereDate('due_at', '<', now()->toDateString())
                ->whereNotNull('client_id')
                ->orderBy('due_at')
                ->each(function (Receivable $receivable) use ($notifyOverdueReceivableToClient, &$notified, &$skipped): void {
                    if ($notifyOverdueReceivableToClient->execute($receivable)) {
                        $notified++;
                    } else {
                        $skipped++;
                    }
                });

            return [
                'notified' => $notified,
                'skipped' => $skipped,
            ];
        });

        $this->info(sprintf(
            'Cobranças notificadas: %d | ignoradas (cooldown): %d',
            $meta['notified'] ?? 0,
            $meta['skipped'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
