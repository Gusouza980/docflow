<?php

namespace App\Automations\Actions;

use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateDocumentRequestAction
{
    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(AutomationRule $rule, Model $subject, array $params, array $context = []): array
    {
        $clientId = $subject instanceof Client
            ? $subject->id
            : ($context['client_id'] ?? null);

        if (! $clientId) {
            throw new InvalidArgumentException('client_id é obrigatório para create_document_request.');
        }

        $items = $params['items'] ?? null;

        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('items é obrigatório para create_document_request.');
        }

        $request = DocumentRequest::query()->create([
            'organization_id' => $rule->organization_id,
            'client_id' => $clientId,
            'title' => $params['title'] ?? 'Solicitação automática de documentos',
            'status' => DocumentRequest::STATUS_PENDING,
            'due_at' => now()->addDays((int) ($params['due_in_days'] ?? 7))->toDateString(),
            'instructions' => $params['instructions'] ?? 'Criada por automação.',
        ]);

        foreach ($items as $item) {
            $title = is_array($item) ? ($item['title'] ?? null) : (is_string($item) ? $item : null);

            if (! is_string($title) || trim($title) === '') {
                throw new InvalidArgumentException('Cada item da solicitação precisa de um título.');
            }

            $request->items()->create([
                'organization_id' => $rule->organization_id,
                'title' => trim($title),
                'document_category_id' => is_array($item) ? ($item['document_category_id'] ?? null) : null,
                'status' => DocumentRequestItem::STATUS_REQUESTED,
            ]);
        }

        return ['document_request_id' => $request->id, 'items' => $request->items()->count()];
    }
}
