<?php

namespace App\Console\Commands\Finance;

use App\Actions\Finance\MarkDelinquentClients;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class MarkDelinquentClientsCommand extends Command
{
    protected $signature = 'finance:mark-delinquent-clients';

    protected $description = 'Marca clientes com saldo vencido há 30+ dias como inadimplentes';

    public function handle(MarkDelinquentClients $markDelinquentClients, SchedulerRunLogger $schedulerRunLogger): int
    {
        $meta = $schedulerRunLogger->run($this->signature, function () use ($markDelinquentClients): array {
            $marked = $markDelinquentClients->execute();

            return ['marked_clients' => count($marked), 'client_ids' => $marked];
        });

        $this->info(sprintf('Clientes marcados como inadimplentes: %d', $meta['marked_clients'] ?? 0));

        return self::SUCCESS;
    }
}
