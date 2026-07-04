<?php

namespace App\Exceptions;

use Exception;

class PlanFeatureUnavailableException extends Exception
{
    public function __construct(
        public readonly string $feature,
        ?string $message = null,
    ) {
        parent::__construct($message ?? $this->defaultMessage($feature));
    }

    private function defaultMessage(string $feature): string
    {
        $label = config("docflow.plan_features.{$feature}", $feature);

        return "O recurso \"{$label}\" não está incluído no plano atual. Faça upgrade para habilitar.";
    }
}
