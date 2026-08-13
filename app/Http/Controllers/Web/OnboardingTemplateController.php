<?php

namespace App\Http\Controllers\Web;

use App\Actions\Crm\StartClientOnboarding;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StartClientOnboardingRequest;
use App\Http\Requests\Web\StoreOnboardingTemplateRequest;
use App\Models\Client;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use App\Models\OrganizationMember;
use App\Support\Billing\PlanLimitChecker;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OnboardingTemplateController extends Controller
{
    public function index(
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): Response|RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);

        $templates = OnboardingTemplate::query()
            ->with('items')
            ->where('organization_id', $membership->organization_id)
            ->latest('id')
            ->get()
            ->map(fn (OnboardingTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'is_active' => $template->is_active,
                'items' => $template->items->map(fn (OnboardingTemplateItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'due_in_days' => $item->due_in_days,
                    'sort_order' => $item->sort_order,
                ]),
            ]);

        $clients = Client::query()
            ->where('organization_id', $membership->organization_id)
            ->orderBy('display_name')
            ->limit(200)
            ->get(['id', 'display_name']);

        return Inertia::render('Onboarding/Templates', [
            'templates' => $templates,
            'clients' => $clients,
            'can' => [
                'manage' => true,
            ],
        ]);
    }

    public function store(
        StoreOnboardingTemplateRequest $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);

        $data = $request->validated();

        DB::transaction(function () use ($membership, $data): void {
            $template = OnboardingTemplate::query()->create([
                'organization_id' => $membership->organization_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['items'] as $index => $item) {
                OnboardingTemplateItem::query()->create([
                    'onboarding_template_id' => $template->id,
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'due_in_days' => $item['due_in_days'] ?? 0,
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()
            ->route('onboarding-templates.index')
            ->with('status', 'Template de onboarding criado.');
    }

    public function start(
        StartClientOnboardingRequest $request,
        OnboardingTemplate $template,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
        StartClientOnboarding $startClientOnboarding,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'crm');
        abort_unless($template->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_unless(
            in_array($membership->role, [
                OrganizationMember::ROLE_ADMIN,
                OrganizationMember::ROLE_MANAGER,
                OrganizationMember::ROLE_PROFESSIONAL,
            ], true),
            HttpResponse::HTTP_FORBIDDEN,
        );

        $client = Client::query()->findOrFail($request->validated('client_id'));
        abort_unless($client->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $client);

        try {
            $tasks = $startClientOnboarding->execute(
                client: $client,
                template: $template,
                actorUserId: $request->user()->id,
                assignedMemberId: $membership->id,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('tasks.index')
            ->with('status', "{$tasks->count()} tarefas de onboarding criadas.");
    }
}
