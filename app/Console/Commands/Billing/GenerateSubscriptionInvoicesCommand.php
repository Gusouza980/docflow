<?php

namespace App\Console\Commands\Billing;

use App\Actions\Billing\GenerateSubscriptionInvoices;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class GenerateSubscriptionInvoicesCommand extends Command
{
    protected $signature = 'billing:generate-invoices';

    protected $description = 'Gera faturas de assinatura pós-trial e de renovação mensal';

    public function handle(
        GenerateSubscriptionInvoices $generateSubscriptionInvoices,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, fn (): array => $generateSubscriptionInvoices->execute());

        $this->info(sprintf(
            'Faturas geradas: %d · Assinaturas canceladas no fim do período: %d',
            $meta['generated'] ?? 0,
            $meta['canceled'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
