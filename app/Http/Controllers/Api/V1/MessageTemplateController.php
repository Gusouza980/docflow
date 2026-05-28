<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMessageTemplateRequest;
use App\Http\Resources\MessageTemplateResource;
use App\Models\MessageTemplate;
use App\Models\OrganizationMember;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MessageTemplateController extends Controller
{
    public function index(Request $request, OrganizationContext $organizationContext): AnonymousResourceCollection
    {
        $templates = MessageTemplate::query()
            ->whereBelongsTo($organizationContext->organization())
            ->when($request->string('channel')->toString(), fn ($query, string $channel) => $query->where('channel', $channel))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return MessageTemplateResource::collection($templates);
    }

    public function store(StoreMessageTemplateRequest $request, OrganizationContext $organizationContext): MessageTemplateResource
    {
        $this->abortIfReadonly($organizationContext);

        $template = MessageTemplate::create([
            ...$request->validated(),
            'organization_id' => $organizationContext->id(),
            'created_by_user_id' => $request->user()->id,
        ]);

        return new MessageTemplateResource($template);
    }

    public function update(StoreMessageTemplateRequest $request, MessageTemplate $template, OrganizationContext $organizationContext): MessageTemplateResource
    {
        $this->authorizeTemplate($template, $organizationContext);
        $this->abortIfReadonly($organizationContext);

        $template->update($request->validated());

        return new MessageTemplateResource($template);
    }

    public function destroy(MessageTemplate $template, OrganizationContext $organizationContext): \Illuminate\Http\Response
    {
        $this->authorizeTemplate($template, $organizationContext);
        $this->abortIfReadonly($organizationContext);

        $template->delete();

        return response()->noContent();
    }

    private function authorizeTemplate(MessageTemplate $template, OrganizationContext $organizationContext): void
    {
        abort_if($template->organization_id !== $organizationContext->id(), Response::HTTP_NOT_FOUND);
    }

    private function abortIfReadonly(OrganizationContext $organizationContext): void
    {
        abort_if($organizationContext->membership()?->role === OrganizationMember::ROLE_READONLY, Response::HTTP_FORBIDDEN);
    }
}
