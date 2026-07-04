<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class DocflowHealthCommand extends Command
{
    protected $signature = 'docflow:health';

    protected $description = 'Valida configuração de fila e e-mail para ambientes de staging/produção';

    public function handle(): int
    {
        $issues = $this->collectIssues();

        if ($issues === []) {
            $this->components->info('Docflow: configuração de fila e e-mail OK.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->components->error($issue);
        }

        return self::FAILURE;
    }

    /**
     * @return array<int, string>
     */
    public function collectIssues(): array
    {
        $issues = [];
        $environment = (string) config('app.env');
        $isProductionLike = in_array($environment, ['production', 'staging'], true);

        if ($isProductionLike && config('mail.default') === 'log') {
            $issues[] = 'MAIL_MAILER não pode ser "log" em produção/staging.';
        }

        if ($isProductionLike && config('queue.default') === 'sync') {
            $issues[] = 'QUEUE_CONNECTION não pode ser "sync" em produção/staging.';
        }

        if (blank(config('mail.from.address'))) {
            $issues[] = 'MAIL_FROM_ADDRESS não está configurado.';
        }

        $queueConnection = (string) config('queue.default');

        try {
            if ($queueConnection === 'database') {
                DB::connection(config('queue.connections.database.connection'))->table('jobs')->limit(1)->count();
            } else {
                Queue::connection($queueConnection);
            }
        } catch (\Throwable $exception) {
            $issues[] = 'Falha ao validar fila ('.$queueConnection.'): '.$exception->getMessage();
        }

        return $issues;
    }
}
