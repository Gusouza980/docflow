<?php

namespace App\Http\Controllers\Web;

use App\Actions\Communication\PreviewMessageBatchRecipients;
use App\Actions\Communication\SendMessageBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\PreviewMessageBatchRequest;
use App\Http\Requests\Web\StoreMessageBatchRequest;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\OrganizationMember;
use App\Support\Communication\WhatsAppLink;
use App\Support\DisplayFormat;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MessageBatchController extends Controller
{
    public function create(
        PreviewMessageBatchRequest $request,
        WebOrganizationContext $webOrganizationContext,
        PreviewMessageBatchRecipients $previewMessageBatchRecipients,
    ): Response|RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);

        $data = $request->validated();
        $template = isset($data['message_template_id'])
            ? MessageTemplate::query()
                ->where('organization_id', $membership->organization_id)
                ->where('is_active', true)
                ->find($data['message_template_id'])
            : null;

        $preview = null;

        if ($template !== null && filled($data['filter'] ?? null)) {
            $preview = $previewMessageBatchRecipients->execute(
                $membership->organization,
                $request->user(),
                $template,
                $data['filter'],
                array_map('intval', $data['client_ids'] ?? []),
            );
        }

        return Inertia::render('Messages/Batch', [
            'filters' => [
                'filter' => $data['filter'] ?? MessageBatch::FILTER_OVERDUE,
                'message_template_id' => $template?->id,
                'client_ids' => array_map('intval', $data['client_ids'] ?? []),
            ],
            'preview' => $preview,
            'options' => [
                'filters' => collect(MessageBatch::filterLabels())
                    ->map(fn (string $label, string $value): array => [
                        'value' => $value,
                        'label' => $label,
                    ])
                    ->values(),
                'templates' => MessageTemplate::query()
                    ->where('organization_id', $membership->organization_id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'channel'])
                    ->map(fn (MessageTemplate $item): array => [
                        'value' => $item->id,
                        'label' => $item->name.' ('.$this->channelLabel($item->channel).')',
                        'channel' => $item->channel,
                    ]),
                'clients' => Client::query()
                    ->where('organization_id', $membership->organization_id)
                    ->orderBy('display_name')
                    ->get(['id', 'display_name'])
                    ->map(fn (Client $client): array => [
                        'value' => $client->id,
                        'label' => $client->display_name,
                    ]),
            ],
            'can' => [
                'send' => $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(
        StoreMessageBatchRequest $request,
        WebOrganizationContext $webOrganizationContext,
        PreviewMessageBatchRecipients $previewMessageBatchRecipients,
        SendMessageBatch $sendMessageBatch,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        $data = $request->validated();

        $template = MessageTemplate::query()
            ->where('organization_id', $membership->organization_id)
            ->where('is_active', true)
            ->findOrFail($data['message_template_id']);

        $preview = $previewMessageBatchRecipients->execute(
            $membership->organization,
            $request->user(),
            $template,
            $data['filter'],
            array_map('intval', $data['client_ids'] ?? []),
        );

        if ($preview['ready'] === []) {
            return redirect()
                ->route('messages.batch.create', [
                    'filter' => $data['filter'],
                    'message_template_id' => $template->id,
                    'client_ids' => $data['client_ids'] ?? [],
                ])
                ->with('error', 'Nenhum destinatário pronto para envio. Revise consentimento e contatos.');
        }

        $batch = $sendMessageBatch->execute(
            $membership->organization,
            $request->user(),
            $template,
            $data['filter'],
            array_map('intval', $data['client_ids'] ?? []),
        );

        $sent = $batch->messages()->count();

        return redirect()
            ->route('messages.batches.show', $batch)
            ->with('status', "Lote criado: {$sent} na fila, {$batch->skipped_count} pulados.");
    }

    public function show(
        MessageBatch $batch,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        WhatsAppLink $whatsAppLink,
    ): Response|RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_unless($batch->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);

        $batch->load(['template', 'messages.client.contacts']);

        return Inertia::render('Messages/BatchShow', [
            'batch' => [
                'id' => $batch->id,
                'filter_label' => MessageBatch::filterLabels()[$batch->filter] ?? $batch->filter,
                'channel' => $batch->channel,
                'channel_label' => $this->channelLabel($batch->channel),
                'template_name' => $batch->template?->name,
                'skipped_count' => $batch->skipped_count,
                'created_at' => DisplayFormat::dateTime($batch->created_at),
            ],
            'messages' => $batch->messages
                ->map(fn (ClientMessage $message): array => [
                    'id' => $message->id,
                    'client_id' => $message->client_id,
                    'client_name' => $message->client->display_name,
                    'status' => $message->status,
                    'failure_reason' => $message->failure_reason,
                    'body' => $message->body,
                    'whatsapp_url' => $message->channel === MessageTemplate::CHANNEL_WHATSAPP
                        ? $whatsAppLink->forClient($message->client, $message->body)
                        : null,
                    'can_open_whatsapp' => $message->channel === MessageTemplate::CHANNEL_WHATSAPP
                        && $message->status === ClientMessage::STATUS_REGISTERED,
                ])
                ->values(),
        ]);
    }

    private function membership(Request $request, WebOrganizationContext $webOrganizationContext): OrganizationMember
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);

        return $membership;
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            MessageTemplate::CHANNEL_EMAIL => 'E-mail',
            MessageTemplate::CHANNEL_WHATSAPP => 'WhatsApp',
            MessageTemplate::CHANNEL_PORTAL => 'Portal',
            MessageTemplate::CHANNEL_PHONE => 'Telefone',
            default => $channel,
        };
    }
}
