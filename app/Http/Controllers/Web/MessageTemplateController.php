<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Models\OrganizationMember;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MessageTemplateController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para gerenciar modelos.');
        }

        $templates = MessageTemplate::query()
            ->whereBelongsTo($membership->organization)
            ->orderBy('name')
            ->get();

        return Inertia::render('MessageTemplates/Index', [
            'templates' => $templates->map(fn (MessageTemplate $template): array => $this->templateSummary($template))->values(),
            'options' => [
                'channels' => [
                    ['value' => MessageTemplate::CHANNEL_EMAIL, 'label' => 'E-mail'],
                    ['value' => MessageTemplate::CHANNEL_WHATSAPP, 'label' => 'WhatsApp'],
                    ['value' => MessageTemplate::CHANNEL_PHONE, 'label' => 'Telefone'],
                    ['value' => MessageTemplate::CHANNEL_PORTAL, 'label' => 'Portal'],
                ],
            ],
            'can' => [
                'create' => $request->user()->can('create', MessageTemplate::class) && $membership->role !== OrganizationMember::ROLE_READONLY,
                'update' => $membership->role !== OrganizationMember::ROLE_READONLY,
            ],
        ]);
    }

    public function store(
        StoreMessageTemplateRequest $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        Gate::authorize('create', MessageTemplate::class);

        $template = MessageTemplate::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
        ]);

        $auditLog->execute('web.message_template.created', $request->user(), $membership->organization, $template, request: $request);

        return redirect()->route('message-templates.index')->with('status', 'Modelo de mensagem criado.');
    }

    public function update(
        StoreMessageTemplateRequest $request,
        MessageTemplate $template,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($template->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $template);

        $template->update($request->validated());

        $auditLog->execute('web.message_template.updated', $request->user(), $membership->organization, $template, request: $request);

        return redirect()->route('message-templates.index')->with('status', 'Modelo de mensagem atualizado.');
    }

    public function destroy(
        MessageTemplate $template,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->membership($request, $webOrganizationContext);
        abort_if($template->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $template);

        $template->delete();

        $auditLog->execute('web.message_template.deleted', $request->user(), $membership->organization, $template, request: $request);

        return redirect()->route('message-templates.index')->with('status', 'Modelo de mensagem removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSummary(MessageTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'channel' => $template->channel,
            'purpose' => $template->purpose,
            'subject' => $template->subject,
            'body' => $template->body,
            'requires_consent' => $template->requires_consent,
            'is_active' => $template->is_active,
        ];
    }

    private function membership(Request $request, WebOrganizationContext $webOrganizationContext): OrganizationMember
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_if($membership->role === OrganizationMember::ROLE_READONLY, HttpResponse::HTTP_FORBIDDEN);

        return $membership;
    }
}
