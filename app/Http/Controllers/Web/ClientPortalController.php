<?php

namespace App\Http\Controllers\Web;

use App\Actions\Documents\ReceiveDocumentRequestItemUpload;
use App\Actions\Notifications\NotifyTeamMembers;
use App\Actions\Organizations\RecordAuditLog;
use App\Actions\Tickets\StoreTicketMessageAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ConfirmClientPortalMeetingRequest;
use App\Http\Requests\Web\StoreClientPortalMessageRequest;
use App\Http\Requests\Web\StoreClientPortalTicketFromMessageRequest;
use App\Http\Requests\Web\StoreClientPortalTicketMessageRequest;
use App\Http\Requests\Web\StoreClientPortalTicketRatingRequest;
use App\Http\Requests\Web\StoreClientPortalTicketRequest;
use App\Http\Requests\Web\UpdateClientPortalProfileRequest;
use App\Http\Requests\Web\UploadClientPortalDocumentRequestItemRequest;
use App\Models\CalendarEvent;
use App\Models\ClientMessage;
use App\Models\ClientPortalAccess;
use App\Models\ClientProfileUpdateRequest;
use App\Models\CommunicationConsent;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentVersion;
use App\Models\GeneratedReport;
use App\Models\InternalReminder;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketMessageAttachment;
use App\Models\TicketRating;
use App\Support\BuildsClientPortalDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientPortalController extends Controller
{
    public function __construct(
        private BuildsClientPortalDashboard $dashboard,
        private ReceiveDocumentRequestItemUpload $receiveDocumentUpload,
        private StoreTicketMessageAttachment $storeTicketAttachment,
        private NotifyTeamMembers $notifyTeam,
    ) {}

    public function dashboard(): Response
    {
        $access = $this->touchPortalAccess();

        return Inertia::render('ClientPortal/Home', [
            'summary' => $this->dashboard->homeSummary($access),
        ]);
    }

    public function messages(): Response
    {
        $access = $this->touchPortalAccess();
        $this->dashboard->markMessagesRead($access);

        return Inertia::render('ClientPortal/Messages', [
            'hasPortalCommunicationConsent' => $this->dashboard->hasPortalConsent($access->client),
            'messages' => $this->dashboard->messagesForAccess($access),
        ]);
    }

    public function documents(): Response
    {
        $access = $this->touchPortalAccess();

        return Inertia::render('ClientPortal/Documents/Index', [
            'documentRequests' => $this->dashboard->documentRequestsForAccess($access),
        ]);
    }

    public function showDocumentRequest(DocumentRequest $documentRequest): Response
    {
        $access = $this->touchPortalAccess();
        abort_unless($documentRequest->client_id === $access->client_id, 404);

        return Inertia::render('ClientPortal/Documents/Show', [
            'documentRequest' => $this->dashboard->documentRequestDetail($documentRequest),
        ]);
    }

    public function uploadDocumentItem(
        UploadClientPortalDocumentRequestItemRequest $request,
        DocumentRequestItem $item,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $access = $this->touchPortalAccess();
        abort_unless($item->documentRequest->client_id === $access->client_id, 404);

        if (! in_array($item->status, [DocumentRequestItem::STATUS_REQUESTED, DocumentRequestItem::STATUS_REJECTED], true)) {
            return redirect()
                ->route('client-portal.documents.show', $item->documentRequest)
                ->with('error', 'Este item não aceita novos envios no momento.');
        }

        $data = $request->validated();
        $this->receiveDocumentUpload->execute(
            $item,
            $request->file('file'),
            null,
            [
                'title' => $data['title'] ?? null,
                'source' => DocumentVersion::SOURCE_PORTAL,
            ],
        );

        $item->refresh();
        $this->notifyTeam->execute(
            $access->organization,
            $item,
            InternalReminder::TYPE_DOCUMENT_RECEIVED_PORTAL,
            $access->client,
        );

        $auditLog->execute('portal.document_request_item.uploaded', null, $access->organization, $item, request: $request);

        return redirect()
            ->route('client-portal.documents.show', $item->documentRequest)
            ->with('status', 'Documento enviado com sucesso.');
    }

    public function tickets(): Response
    {
        $access = $this->touchPortalAccess();

        return Inertia::render('ClientPortal/Tickets/Index', [
            'tickets' => $this->dashboard->ticketsForAccess($access),
            'selectedTicketId' => request()->integer('ticket') ?: null,
        ]);
    }

    public function finance(): Response
    {
        $access = $this->touchPortalAccess();

        return Inertia::render('ClientPortal/Finance', [
            ...$this->dashboard->financePageForAccess($access),
            'highlightReceivableId' => request()->integer('receivable') ?: null,
        ]);
    }

    public function more(): Response
    {
        $access = $this->touchPortalAccess();

        return Inertia::render('ClientPortal/More', [
            'announcements' => $this->dashboard->announcementsForAccess($access),
            'reports' => $this->dashboard->reportsForAccess($access),
        ]);
    }

    public function meetings(): Response
    {
        $access = $this->touchPortalAccess();

        return Inertia::render('ClientPortal/Meetings', [
            'meetings' => $this->dashboard->meetingsForAccess($access),
        ]);
    }

    public function profile(): Response
    {
        $access = $this->touchPortalAccess();

        return Inertia::render('ClientPortal/Profile', [
            'profile' => $this->dashboard->profileForAccess($access),
        ]);
    }

    public function updateProfile(UpdateClientPortalProfileRequest $request): RedirectResponse
    {
        $access = $this->portalAccess();
        $data = $request->validated();
        $currentProfile = $this->dashboard->profileForAccess($access);

        $access->update(['name' => $data['name']]);

        $reviewChanges = [];

        if (($data['email'] ?? null) && $data['email'] !== $currentProfile['email']) {
            $reviewChanges['email'] = $data['email'];
        }

        if (array_key_exists('phone', $data) && ($data['phone'] ?? '') !== ($currentProfile['phone'] ?? '')) {
            $reviewChanges['phone'] = $data['phone'];
        }

        if (array_key_exists('whatsapp', $data) && ($data['whatsapp'] ?? '') !== ($currentProfile['whatsapp'] ?? '')) {
            $reviewChanges['whatsapp'] = $data['whatsapp'];
        }

        if ($reviewChanges !== []) {
            ClientProfileUpdateRequest::query()
                ->where('client_portal_access_id', $access->id)
                ->where('status', ClientProfileUpdateRequest::STATUS_PENDING)
                ->delete();

            $profileUpdate = ClientProfileUpdateRequest::create([
                'organization_id' => $access->organization_id,
                'client_id' => $access->client_id,
                'client_portal_access_id' => $access->id,
                'changes' => $reviewChanges,
            ]);

            $this->notifyTeam->execute(
                $access->organization,
                $profileUpdate,
                InternalReminder::TYPE_PORTAL_PROFILE_UPDATE,
                $access->client,
                includeManagers: true,
            );
        }

        $message = $reviewChanges !== []
            ? 'Nome atualizado. Demais alterações aguardam revisão da equipe.'
            : 'Dados atualizados com sucesso.';

        return redirect()->route('client-portal.profile')->with('status', $message);
    }

    public function confirmMeeting(
        ConfirmClientPortalMeetingRequest $request,
        CalendarEvent $event,
    ): RedirectResponse {
        $access = $this->portalAccess();
        abort_unless($event->client_id === $access->client_id, 404);

        if (! $this->dashboard->meetingCanBeConfirmed($event, $access)) {
            return redirect()->route('client-portal.meetings')
                ->with('error', 'Esta reunião não está disponível para confirmação.');
        }

        $action = $request->validated('action');
        $notes = $request->validated('notes');

        $event->update([
            'portal_confirmation_status' => $action,
            'portal_confirmation_notes' => $notes,
            'portal_confirmed_at' => now(),
            'portal_confirmed_by_access_id' => $access->id,
            'status' => $action === CalendarEvent::PORTAL_CONFIRMATION_CONFIRMED
                ? CalendarEvent::STATUS_CONFIRMED
                : $event->status,
        ]);

        if ($event->created_by_user_id) {
            $this->notifyTeam->execute(
                $access->organization,
                $event,
                InternalReminder::TYPE_MEETING_PORTAL_CONFIRMATION,
                $access->client,
                extraUserIds: [$event->created_by_user_id],
            );
        }

        $statusMessage = match ($action) {
            CalendarEvent::PORTAL_CONFIRMATION_CONFIRMED => 'Reunião confirmada. Obrigado!',
            CalendarEvent::PORTAL_CONFIRMATION_DECLINED => 'Presença recusada. A equipe será notificada.',
            default => 'Solicitação de remarcação enviada. A equipe entrará em contato.',
        };

        return redirect()->route('client-portal.meetings')->with('status', $statusMessage);
    }

    public function downloadReport(GeneratedReport $report): StreamedResponse
    {
        $access = $this->portalAccess();
        abort_unless(
            $report->client_id === $access->client_id
            && $report->status === GeneratedReport::STATUS_RELEASED,
            404,
        );

        $report->update(['last_viewed_at' => now()]);

        $filename = str($report->title)->slug()->append('.json')->toString();
        $payload = json_encode([
            'title' => $report->title,
            'released_at' => $report->released_at?->toIso8601String(),
            'data' => $report->payload,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(
            static function () use ($payload): void {
                echo $payload;
            },
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function storeTicketFromMessage(
        StoreClientPortalTicketFromMessageRequest $request,
        ClientMessage $message,
    ): RedirectResponse {
        $access = $this->portalAccess();
        abort_unless($message->client_id === $access->client_id, 404);
        abort_unless($message->direction === ClientMessage::DIRECTION_OUTBOUND, 404);
        abort_if($message->ticket_id !== null, 404);

        $ticket = DB::transaction(function () use ($request, $access, $message): Ticket {
            $ticket = Ticket::create([
                'organization_id' => $access->organization_id,
                'client_id' => $access->client_id,
                'source_message_id' => $message->id,
                'title' => $request->validated('title') ?: ($message->subject ?: 'Solicitação sobre mensagem'),
                'description' => $message->body,
                'visible_to_client' => true,
            ]);

            $ticket->messages()->create([
                'organization_id' => $access->organization_id,
                'client_portal_access_id' => $access->id,
                'sender_type' => TicketMessage::SENDER_CLIENT,
                'body' => "Chamado aberto a partir de mensagem do escritório:\n\n{$message->body}",
                'visible_to_client' => true,
            ]);

            $message->update(['ticket_id' => $ticket->id]);

            return $ticket;
        });

        $this->notifyTeam->execute(
            $access->organization,
            $ticket,
            InternalReminder::TYPE_PORTAL_TICKET_OPENED,
            $access->client,
        );

        return redirect()
            ->route('client-portal.tickets.index', ['ticket' => $ticket->id])
            ->with('status', 'Chamado aberto a partir da mensagem.');
    }

    public function showTicket(Ticket $ticket): JsonResponse
    {
        $access = $this->portalAccess();
        $this->dashboard->markTicketRead($ticket, $access);

        return response()->json([
            'ticket' => $this->dashboard->ticketDetailForAccess($ticket, $access),
        ]);
    }

    public function ticketMessages(Request $request, Ticket $ticket): JsonResponse
    {
        $access = $this->portalAccess();
        abort_unless($ticket->client_id === $access->client_id && $ticket->visible_to_client, 404);

        return response()->json([
            'messages' => $this->dashboard->ticketMessagesForAccess(
                $ticket,
                $access,
                $request->integer('since_id') ?: null,
            ),
        ]);
    }

    public function downloadTicketAttachment(TicketMessageAttachment $attachment): StreamedResponse
    {
        $access = $this->portalAccess();
        $attachment->loadMissing('message.ticket');
        abort_unless(
            $attachment->message->ticket->client_id === $access->client_id
            && $attachment->message->ticket->visible_to_client
            && $attachment->message->visible_to_client
            && $attachment->visible_to_client,
            404,
        );

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function storeTicketMessage(StoreClientPortalTicketMessageRequest $request, Ticket $ticket): RedirectResponse
    {
        $access = $this->portalAccess();
        abort_unless($ticket->client_id === $access->client_id && $ticket->visible_to_client, 404);

        if (! $this->dashboard->ticketCanBeRepliedByClient($ticket)) {
            return redirect()->route('client-portal.tickets.index', ['ticket' => $ticket->id])
                ->with('error', 'Este chamado está encerrado e não aceita novas mensagens.');
        }

        $message = $ticket->messages()->create([
            'organization_id' => $access->organization_id,
            'client_portal_access_id' => $access->id,
            'sender_type' => TicketMessage::SENDER_CLIENT,
            'body' => $request->validated('body') ?? '',
            'visible_to_client' => true,
        ]);

        if ($request->hasFile('attachment')) {
            $this->storeTicketAttachment->execute($message, $request->file('attachment'), true);
            $this->notifyTeam->execute(
                $access->organization,
                $message,
                InternalReminder::TYPE_TICKET_CLIENT_ATTACHMENT,
                $access->client,
            );
        } else {
            $this->notifyTeam->execute(
                $access->organization,
                $message,
                InternalReminder::TYPE_TICKET_CLIENT_REPLY,
                $access->client,
            );
        }

        if (in_array($ticket->status, [Ticket::STATUS_WAITING_CLIENT, Ticket::STATUS_RESOLVED], true)) {
            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'resolved_at' => null,
            ]);
        }

        return redirect()->route('client-portal.tickets.index', ['ticket' => $ticket->id]);
    }

    public function storeTicketRating(StoreClientPortalTicketRatingRequest $request, Ticket $ticket): RedirectResponse
    {
        $access = $this->portalAccess();
        abort_unless($ticket->client_id === $access->client_id && $ticket->visible_to_client, 404);

        if (! $this->dashboard->ticketCanBeRatedByClient($ticket)) {
            return redirect()->route('client-portal.tickets.index', ['ticket' => $ticket->id])
                ->with('error', 'Este chamado não está disponível para avaliação.');
        }

        $ticket->loadMissing('rating');

        if ($ticket->rating !== null) {
            return redirect()->route('client-portal.tickets.index', ['ticket' => $ticket->id])
                ->with('error', 'Este chamado já foi avaliado.');
        }

        TicketRating::create([
            'organization_id' => $access->organization_id,
            'ticket_id' => $ticket->id,
            'client_portal_access_id' => $access->id,
            'rating' => $request->integer('rating'),
            'comment' => $request->validated('comment'),
        ]);

        $ticket->refresh()->load('rating');

        if ($request->integer('rating') <= 2) {
            $this->notifyTeam->execute(
                $access->organization,
                $ticket,
                InternalReminder::TYPE_TICKET_LOW_RATING,
                $access->client,
                includeManagers: true,
            );
        }

        return redirect()->route('client-portal.tickets.index', ['ticket' => $ticket->id])
            ->with('status', 'Obrigado pela sua avaliação!');
    }

    public function pollMessages(Request $request): JsonResponse
    {
        $access = $this->portalAccess();
        $sinceId = $request->integer('since_id') ?: null;

        return response()->json([
            'messages' => $this->dashboard->messagesForAccess($access, $sinceId),
        ]);
    }

    public function storeConsent(): RedirectResponse
    {
        $access = $this->portalAccess();

        CommunicationConsent::updateOrCreate([
            'organization_id' => $access->organization_id,
            'client_id' => $access->client_id,
            'channel' => 'portal',
            'purpose' => 'general',
        ], [
            'recorded_by_user_id' => null,
            'status' => CommunicationConsent::STATUS_GRANTED,
            'source' => 'client_portal',
            'notes' => "Consentimento concedido por {$access->name} ({$access->email}) via portal.",
            'granted_at' => now(),
            'revoked_at' => null,
        ]);

        return redirect()->route('client-portal.messages');
    }

    public function storeMessage(StoreClientPortalMessageRequest $request): RedirectResponse
    {
        $access = $this->portalAccess();

        if (! $this->dashboard->hasPortalConsent($access->client)) {
            return redirect()->route('client-portal.messages')
                ->with('error', 'Autorize a comunicação pelo portal para enviar mensagens.');
        }

        $message = ClientMessage::create([
            'organization_id' => $access->organization_id,
            'client_id' => $access->client_id,
            'client_portal_access_id' => $access->id,
            'channel' => 'portal',
            'direction' => ClientMessage::DIRECTION_INBOUND,
            'status' => ClientMessage::STATUS_RECEIVED,
            'body' => $request->validated('body'),
            'external_name' => $access->name,
            'external_email' => $access->email,
            'received_at' => now(),
        ]);

        $this->notifyTeam->execute(
            $access->organization,
            $message,
            InternalReminder::TYPE_PORTAL_MESSAGE_INBOUND,
            $access->client,
        );

        return redirect()->route('client-portal.messages');
    }

    public function storeTicket(StoreClientPortalTicketRequest $request): RedirectResponse
    {
        $access = $this->portalAccess();

        $ticket = Ticket::create([
            'organization_id' => $access->organization_id,
            'client_id' => $access->client_id,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'visible_to_client' => true,
        ]);

        $ticket->messages()->create([
            'organization_id' => $access->organization_id,
            'client_portal_access_id' => $access->id,
            'sender_type' => TicketMessage::SENDER_CLIENT,
            'body' => $request->validated('description'),
            'visible_to_client' => true,
        ]);

        $this->notifyTeam->execute(
            $access->organization,
            $ticket,
            InternalReminder::TYPE_PORTAL_TICKET_OPENED,
            $access->client,
        );

        return redirect()->route('client-portal.tickets.index', ['ticket' => $ticket->id])->with('status', 'Solicitação aberta.');
    }

    private function touchPortalAccess(): ClientPortalAccess
    {
        $access = $this->portalAccess();
        $access->update(['last_used_at' => now()]);

        return $access;
    }

    private function portalAccess(): ClientPortalAccess
    {
        /** @var ClientPortalAccess $access */
        $access = auth('portal')->user();

        return $access->loadMissing(['organization', 'client']);
    }
}
