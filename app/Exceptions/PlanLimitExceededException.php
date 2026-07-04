<?php

namespace App\Exceptions;

use Exception;

class PlanLimitExceededException extends Exception
{
    public function __construct(
        public readonly string $metric,
        public readonly int $limit,
        public readonly int $current,
        ?string $message = null,
    ) {
        parent::__construct($message ?? $this->defaultMessage($metric, $limit, $current));
    }

    private function defaultMessage(string $metric, int $limit, int $current): string
    {
        $label = config("docflow.plan_limits.{$metric}.label", $metric);

        return "Limite do plano atingido para {$label} ({$current}/{$limit}). Entre em contato para fazer upgrade.";
    }
}
