<?php

namespace App\Http\Requests\Concerns;

use App\Support\Money;
use InvalidArgumentException;

trait ConvertsMoneyFields
{
    /**
     * @return list<string>
     */
    abstract protected function moneyFields(): array;

    protected function prepareMoneyFields(): void
    {
        $merged = [];

        foreach ($this->moneyFields() as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $raw = $this->input($field);

            if ($raw === null || $raw === '') {
                $merged[$field] = null;

                continue;
            }

            try {
                $merged[$field] = Money::toCents(
                    is_numeric($raw) && ! is_string($raw) ? $raw : (string) $raw
                );
            } catch (InvalidArgumentException) {
                $merged[$field] = 'invalid-money';
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->moneyFields() as $field) {
            $messages["{$field}.integer"] = 'Informe um valor válido em reais (ex.: 1.250,00).';
        }

        return $messages;
    }
}
