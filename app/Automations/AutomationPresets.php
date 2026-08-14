<?php

namespace App\Automations;

use App\Models\AutomationRule;

class AutomationPresets
{
    /**
     * @return array<string, array{
     *     name: string,
     *     trigger: string,
     *     conditions: array<string, mixed>,
     *     actions: list<array{type: string, params: array<string, mixed>}>
     * }>
     */
    public static function all(): array
    {
        return [
            'client_created_tasks' => [
                'name' => 'Cliente criado → tarefas do modelo',
                'trigger' => AutomationRule::TRIGGER_CLIENT_CREATED,
                'conditions' => [],
                'actions' => [
                    [
                        'type' => AutomationRule::ACTION_CREATE_TASKS_FROM_TEMPLATE,
                        'params' => [
                            'task_template_id' => null,
                        ],
                    ],
                ],
            ],
            'document_expiring_notify' => [
                'name' => 'Documento a vencer → notificar equipe',
                'trigger' => AutomationRule::TRIGGER_DOCUMENT_EXPIRING,
                'conditions' => ['within_days' => 7],
                'actions' => [
                    [
                        'type' => AutomationRule::ACTION_NOTIFY_ORGANIZATION_MEMBERS,
                        'params' => [
                            'roles' => ['admin', 'manager'],
                            'message' => 'Documento próximo do vencimento.',
                        ],
                    ],
                ],
            ],
            'contract_expiring_notify' => [
                'name' => 'Contrato a vencer → notificar equipe',
                'trigger' => AutomationRule::TRIGGER_CONTRACT_EXPIRING,
                'conditions' => ['within_days' => 30],
                'actions' => [
                    [
                        'type' => AutomationRule::ACTION_NOTIFY_ORGANIZATION_MEMBERS,
                        'params' => [
                            'roles' => ['admin', 'manager'],
                            'message' => 'Contrato próximo do vencimento.',
                        ],
                    ],
                ],
            ],
            'receivable_overdue_notify' => [
                'name' => 'Cobrança vencida → notificar equipe',
                'trigger' => AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
                'conditions' => [],
                'actions' => [
                    [
                        'type' => AutomationRule::ACTION_NOTIFY_ORGANIZATION_MEMBERS,
                        'params' => [
                            'roles' => ['admin', 'manager', 'finance'],
                            'message' => 'Cobrança em atraso.',
                        ],
                    ],
                ],
            ],
            'receivable_overdue_email' => [
                'name' => 'Cobrança vencida → e-mail ao cliente',
                'trigger' => AutomationRule::TRIGGER_RECEIVABLE_OVERDUE,
                'conditions' => [],
                'actions' => [
                    [
                        'type' => AutomationRule::ACTION_SEND_MESSAGE_TEMPLATE,
                        'params' => [
                            'message_template_id' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{name: string, trigger: string, conditions: array<string, mixed>, actions: list<array{type: string, params: array<string, mixed>}>}
     */
    public static function get(string $key): array
    {
        $preset = self::all()[$key] ?? null;

        if ($preset === null) {
            throw new \InvalidArgumentException("Preset de automação inválido: {$key}");
        }

        return $preset;
    }
}
