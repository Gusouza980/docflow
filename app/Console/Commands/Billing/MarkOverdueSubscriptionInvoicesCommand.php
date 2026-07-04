<?php

namespace App\Console\Commands\Billing;

use App\Actions\Billing\MarkOverdueSubscriptionInvoices;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class MarkOverdueSubscriptionInvoicesCommand extends Command
{
    protected $signature = 'billing:mark-overdue-invoices';

    protected $description = 'Marca faturas vencidas e assinaturas como inadimplentes';

    public function handle(
        MarkOverdueSubscriptionInvoices $markOverdueSubscriptionInvoices,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, fn (): array => $markOverdueSubscriptionInvoices->execute());

        $this->info(sprintf('Faturas inadimplentes processadas: %d', $meta['marked'] ?? 0));

        return self::SUCCESS;
    }
}
