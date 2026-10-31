<?php

namespace App\Automations\Actions;

use App\Actions\Communication\DeliverOutboundClientMessage;
use App\Models\AutomationRule;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\MessageTemplate;
use App\Models\Receivable;
use App\Support\Communication\ClientMessageDestination;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SendMessageTemplateAction
{
    public function __construct(
        private DeliverOutboundClientMessage $deliverOutboundClientMessage,
        private ClientMessageDestination $destination,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(AutomationRule $rule, Model $subject, array $params, array $context = []): array
    {
        $templateId = (int) ($params['message_template_id'] ?? 0);
        $template = MessageTemplate::query()
            ->where('organization_id', $rule->organization_id)
            ->where('is_active', true)
            ->find($templateId);

        if ($template === null) {
            throw new InvalidArgumentException('Modelo de mensagem inválido ou inativo.');
        }

        if (! in_array($template->channel, [MessageTemplate::CHANNEL_EMAIL, MessageTemplate::CHANNEL_PORTAL], true)) {
            return [
                'skipped' => true,
                'reason' => 'Automação envia só e-mail ou portal. Use o envio em lote para WhatsApp.',
            ];
        }

        $client = $this->resolveClient($subject, $context);

        if ($client === null || $client->organization_id !== $rule->organization_id) {
            throw new InvalidArgumentException('Cliente da automação não encontrado.');
        }

        $receivable = $subject instanceof Receivable ? $subject : null;

        if ($receivable !== null && $this->reminderAlreadySentToday($receivable, $template)) {
            return [
                'skipped' => true,
                'reason' => 'Lembrete deste modelo já saiu hoje.',
                'client_id' => $client->id,
            ];
        }

        $reason = $this->destination->skipReason($client, $template->channel, $template->purpose);

        if ($reason !== null) {
            return [
                'skipped' => true,
                'reason' => $reason,
                'client_id' => $client->id,
            ];
        }

        $message = $this->deliverOutboundClientMessage->queue(
            client: $client,
            channel: $template->channel,
            body: '',
            subject: $template->subject,
            template: $template,
            variables: $this->destination->variablesFor($client, $receivable),
            receivableId: $receivable?->id,
        );

        return [
            'client_message_id' => $message->id,
            'client_id' => $client->id,
            'status' => $message->fresh()?->status ?? $message->status,
            'skipped' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reminderAlreadySentToday(Receivable $receivable, MessageTemplate $template): bool
    {
        if ($receivable->last_portal_reminder_at?->isToday()) {
            return true;
        }

        return ClientMessage::query()
            ->where('receivable_id', $receivable->id)
            ->where('message_template_id', $template->id)
            ->whereDate('created_at', now()->toDateString())
            ->exists();
    }

    private function resolveClient(Model $subject, array $context): ?Client
    {
        if ($subject instanceof Client) {
            return $subject;
        }

        $clientId = $context['client_id'] ?? $subject->getAttribute('client_id');

        if (! $clientId) {
            return null;
        }

        return Client::query()->find($clientId);
    }
}
