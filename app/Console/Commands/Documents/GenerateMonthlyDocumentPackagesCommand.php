<?php

namespace App\Console\Commands\Documents;

use App\Actions\Documents\GenerateMonthlyDocumentPackages;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;

class GenerateMonthlyDocumentPackagesCommand extends Command
{
    protected $signature = 'documents:generate-monthly-packages';

    protected $description = 'Gera o pacote documental do mês para serviços ativos, sem duplicar.';

    public function handle(
        GenerateMonthlyDocumentPackages $generateMonthlyDocumentPackages,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, function () use ($generateMonthlyDocumentPackages): array {
            $generated = $generateMonthlyDocumentPackages->execute();

            return ['generated' => count($generated)];
        });

        $this->info(sprintf('Pacotes documentais gerados: %d', $meta['generated'] ?? 0));

        return self::SUCCESS;
    }
}
