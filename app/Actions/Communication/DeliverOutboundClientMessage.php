<?php

namespace App\Actions\Communication;

use App\Actions\Notifications\NotifyPortalClient;
use App\Contracts\Mail\TransactionalMailer;
use App\Jobs\DeliverClientMessageJob;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Notifications\ClientTemplateMessageNotification;
use App\Support\Communication\ClientMessageDestination;
use App\Support\Mail\MailRecipient;
use Throwable;

class DeliverOutboundClientMessage
{
    public function __construct(
        private ClientMessageDestination $destination,
        private TransactionalMailer $transactionalMailer,
        private NotifyPortalClient $notifyPortalClient,
    ) {}

    /**
     * @param  array<string, string>  $variables
     */
    public function queue(
        Client $client,
        string $channel,
        string $body,
        ?string $subject = null,
        ?MessageTemplate $template = null,
        ?User $sentBy = null,
        ?MessageBatch $batch = null,
        array $variables = [],
        bool $deliverNow = true,
    ): ClientMessage {
        $rawSubject = $subject ?? $template?->subject;
        $renderedSubject = filled($rawSubject) ? $this->renderText((string) $rawSubject, $variables) : null;
        $renderedBody = $body !== ''
            ? $this->renderText($body, $variables)
            : (string) $template?->renderBody($variables);

        $initialStatus = $this->initialStatus($channel);
        $skipReason = $this->destination->skipReason($client, $channel, $template?->purpose ?? 'general');

        if ($skipReason !== null && in_array($channel, [
            MessageTemplate::CHANNEL_EMAIL,
            MessageTemplate::CHANNEL_WHATSAPP,
            MessageTemplate::CHANNEL_PORTAL,
        ], true)) {
            $initialStatus = ClientMessage::STATUS_FAILED;
        }

        $message = ClientMessage::query()->create([
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
            'message_template_id' => $template?->id,
            'batch_id' => $batch?->id,
            'sent_by_user_id' => $sentBy?->id,
            'channel' => $channel,
            'direction' => ClientMessage::DIRECTION_OUTBOUND,
            'status' => $initialStatus,
            'subject' => $renderedSubject,
            'body' => $renderedBody,
            'failure_reason' => $initialStatus === ClientMessage::STATUS_FAILED ? $skipReason : null,
            'sent_at' => $initialStatus === ClientMessage::STATUS_SENT ? now() : null,
        ]);

        if ($message->status === ClientMessage::STATUS_QUEUED) {
            if ($deliverNow && config('queue.default') === 'sync') {
                try {
                    $this->process($message);
                } catch (Throwable $exception) {
                    $message->update([
                        'status' => ClientMessage::STATUS_FAILED,
                        'failure_reason' => $exception->getMessage(),
                    ]);
                }
            } elseif (! $deliverNow && config('queue.default') === 'sync') {
                // Caller delivers after the surrounding transaction commits.
            } else {
                DeliverClientMessageJob::dispatch($message->id)->afterCommit();
            }
        }

        return $message;
    }

    public function process(ClientMessage $message): void
    {
        if ($message->status !== ClientMessage::STATUS_QUEUED) {
            return;
        }

        $message->loadMissing(['client', 'template']);

        $reason = $this->destination->skipReason(
            $message->client,
            $message->channel,
            $message->template?->purpose ?? 'general',
        );

        if ($reason !== null) {
            $message->update([
                'status' => ClientMessage::STATUS_FAILED,
                'failure_reason' => $reason,
            ]);

            return;
        }

        match ($message->channel) {
            MessageTemplate::CHANNEL_EMAIL => $this->sendEmail($message),
            MessageTemplate::CHANNEL_PORTAL => $this->sendPortal($message),
            default => null,
        };

        $message->update([
            'status' => ClientMessage::STATUS_SENT,
            'failure_reason' => null,
            'sent_at' => $message->sent_at ?? now(),
        ]);
    }

    public function markWhatsAppOpened(ClientMessage $message): void
    {
        if ($message->channel !== MessageTemplate::CHANNEL_WHATSAPP) {
            return;
        }

        if ($message->status !== ClientMessage::STATUS_REGISTERED) {
            return;
        }

        $message->update([
            'status' => ClientMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    private function initialStatus(string $channel): string
    {
        return match ($channel) {
            MessageTemplate::CHANNEL_EMAIL, MessageTemplate::CHANNEL_PORTAL => ClientMessage::STATUS_QUEUED,
            MessageTemplate::CHANNEL_WHATSAPP => ClientMessage::STATUS_REGISTERED,
            default => ClientMessage::STATUS_SENT,
        };
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function renderText(string $text, array $variables): string
    {
        return str($text)
            ->replaceMatches('/{{\s*([a-zA-Z0-9_]+)\s*}}/', fn (array $matches): string => $variables[$matches[1]] ?? $matches[0])
            ->toString();
    }

    private function sendEmail(ClientMessage $message): void
    {
        $recipient = $this->destination->emailFor($message->client);

        if ($recipient === null) {
            throw new \RuntimeException('Cadastre um e-mail no contato do cliente.');
        }

        $this->transactionalMailer->notify(
            new MailRecipient($recipient['email'], $recipient['name']),
            new ClientTemplateMessageNotification(
                subject: $message->subject ?: 'Mensagem do escritório',
                body: $message->body,
            ),
        );
    }

    private function sendPortal(ClientMessage $message): void
    {
        $this->notifyPortalClient->execute(
            $message->client,
            $message->subject ?: 'Nova mensagem do escritório',
            $message->body,
            route('client-portal.messages', absolute: true),
        );
    }
}
