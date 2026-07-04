<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\ClientProfileUpdateRequest;
use App\Models\DocumentRequestItem;
use App\Models\InternalReminder;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketMessage;

class PresentsInternalReminder
{
    /**
     * @return array<string, mixed>
     */
    public function present(InternalReminder $reminder): array
    {
        $reminder->loadMissing('remindable');

        $content = $this->contentFor($reminder);

        return [
            'id' => $reminder->id,
            'type' => $reminder->type,
            'title' => $content['title'],
            'body' => $content['body'],
            'url' => $content['url'],
            'read_at' => $reminder->read_at?->toISOString(),
            'is_read' => $reminder->read_at !== null,
            'created_at' => $reminder->created_at?->toISOString(),
            'remind_at' => DisplayFormat::dateTime($reminder->remind_at),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function contentFor(InternalReminder $reminder): array
    {
        $remindable = $reminder->remindable;

        return match ($reminder->type) {
            InternalReminder::TYPE_TASK_ASSIGNED => $this->taskAssigned($remindable),
            InternalReminder::TYPE_CALENDAR_EVENT => $this->calendarEvent($remindable, 'Compromisso na agenda'),
            InternalReminder::TYPE_MEETING_PORTAL_CONFIRMATION => $this->calendarEvent($remindable, 'Cliente respondeu convite de reunião'),
            InternalReminder::TYPE_DOCUMENT_RECEIVED_PORTAL => $this->documentReceived($remindable),
            InternalReminder::TYPE_TICKET_CLIENT_REPLY,
            InternalReminder::TYPE_TICKET_CLIENT_ATTACHMENT => $this->ticketMessageActivity($remindable, $reminder->type),
            InternalReminder::TYPE_TICKET_LOW_RATING => $this->ticketLowRating($remindable),
            InternalReminder::TYPE_PORTAL_MESSAGE_INBOUND => $this->portalMessage($remindable),
            InternalReminder::TYPE_PORTAL_TICKET_OPENED => $this->portalTicketOpened($remindable),
            InternalReminder::TYPE_PORTAL_PROFILE_UPDATE => $this->portalProfileUpdate($remindable),
            InternalReminder::TYPE_CLIENT_DELINQUENT => $this->clientDelinquent($remindable),
            default => [
                'title' => 'Notificação',
                'body' => 'Há uma atualização que requer sua atenção.',
                'url' => route('dashboard'),
            ],
        };
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function taskAssigned(mixed $remindable): array
    {
        if (! $remindable instanceof Task) {
            return $this->missing('Tarefa atribuída');
        }

        return [
            'title' => 'Tarefa atribuída',
            'body' => $remindable->title,
            'url' => route('tasks.index'),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function calendarEvent(mixed $remindable, string $title): array
    {
        if (! $remindable instanceof CalendarEvent) {
            return $this->missing($title);
        }

        return [
            'title' => $title,
            'body' => $remindable->title.' · '.DisplayFormat::dateTime($remindable->starts_at),
            'url' => route('calendar.index'),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function documentReceived(mixed $remindable): array
    {
        if (! $remindable instanceof DocumentRequestItem) {
            return $this->missing('Documento recebido pelo portal');
        }

        $remindable->loadMissing('documentRequest.client');

        return [
            'title' => 'Documento enviado pelo cliente',
            'body' => ($remindable->documentRequest->client?->display_name ?? 'Cliente').': '.$remindable->title,
            'url' => route('document-requests.show', $remindable->documentRequest),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function ticketMessageActivity(mixed $remindable, string $type): array
    {
        if ($remindable instanceof TicketMessage) {
            $remindable->loadMissing('ticket');

            $ticket = $remindable->ticket;
            $title = $type === InternalReminder::TYPE_TICKET_CLIENT_ATTACHMENT
                ? 'Cliente anexou arquivo em chamado'
                : 'Cliente respondeu chamado';

            return [
                'title' => $title,
                'body' => $ticket?->title ?? 'Chamado do portal',
                'url' => $ticket
                    ? route('clients.show', ['client' => $ticket->client_id, 'tab' => 'tickets', 'ticket' => $ticket->id])
                    : route('dashboard'),
            ];
        }

        if ($remindable instanceof Ticket) {
            return $this->ticketClientActivity($remindable, $type);
        }

        return $this->missing('Atualização em chamado');
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function ticketClientActivity(mixed $remindable, string $type): array
    {
        if (! $remindable instanceof Ticket) {
            return $this->missing('Atualização em chamado');
        }

        $title = $type === InternalReminder::TYPE_TICKET_CLIENT_ATTACHMENT
            ? 'Cliente anexou arquivo em chamado'
            : 'Cliente respondeu chamado';

        return [
            'title' => $title,
            'body' => $remindable->title,
            'url' => route('clients.show', ['client' => $remindable->client_id, 'tab' => 'tickets', 'ticket' => $remindable->id]),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function ticketLowRating(mixed $remindable): array
    {
        if (! $remindable instanceof Ticket) {
            return $this->missing('Avaliação baixa em chamado');
        }

        $remindable->loadMissing('rating');

        return [
            'title' => 'Avaliação baixa em chamado',
            'body' => $remindable->title.' · nota '.($remindable->rating?->rating ?? '?'),
            'url' => route('clients.show', ['client' => $remindable->client_id, 'tab' => 'tickets', 'ticket' => $remindable->id]),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function portalMessage(mixed $remindable): array
    {
        if (! $remindable instanceof ClientMessage) {
            return $this->missing('Mensagem do portal');
        }

        $remindable->loadMissing('client');

        return [
            'title' => 'Nova mensagem no portal',
            'body' => ($remindable->client?->display_name ?? 'Cliente').': '.str($remindable->body)->limit(120)->toString(),
            'url' => route('clients.show', ['client' => $remindable->client_id, 'tab' => 'communication']),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function portalTicketOpened(mixed $remindable): array
    {
        if (! $remindable instanceof Ticket) {
            return $this->missing('Chamado aberto pelo portal');
        }

        return [
            'title' => 'Chamado aberto pelo portal',
            'body' => $remindable->title,
            'url' => route('clients.show', ['client' => $remindable->client_id, 'tab' => 'tickets', 'ticket' => $remindable->id]),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function portalProfileUpdate(mixed $remindable): array
    {
        if (! $remindable instanceof ClientProfileUpdateRequest) {
            return $this->missing('Alteração cadastral no portal');
        }

        $remindable->loadMissing(['client', 'portalAccess']);
        $fields = implode(', ', array_keys($remindable->changes ?? []));

        return [
            'title' => 'Alteração cadastral pendente',
            'body' => ($remindable->client?->display_name ?? 'Cliente').' solicitou revisão de: '.$fields,
            'url' => route('portal.index'),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function clientDelinquent(mixed $remindable): array
    {
        if (! $remindable instanceof Client) {
            return $this->missing('Cliente inadimplente');
        }

        return [
            'title' => 'Cliente inadimplente',
            'body' => ($remindable->display_name ?? 'Cliente').' possui cobranças vencidas há mais de '.config('docflow.finance.delinquent_after_days', 30).' dias.',
            'url' => route('clients.show', ['client' => $remindable->id, 'tab' => 'finance']),
        ];
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    private function missing(string $title): array
    {
        return [
            'title' => $title,
            'body' => 'Registro indisponível.',
            'url' => route('dashboard'),
        ];
    }
}
