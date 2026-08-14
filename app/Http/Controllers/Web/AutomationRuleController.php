<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Automations\AutomationPresets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreAutomationRuleRequest;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\MessageTemplate;
use App\Models\OrganizationMember;
use App\Models\TaskTemplate;
use App\Support\Billing\PlanLimitChecker;
use App\Support\DisplayFormat;
use App\Support\WebOrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AutomationRuleController extends Controller
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

        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);
        $planLimitChecker->assertFeature($membership->organization, 'automations');

        $rules = AutomationRule::query()
            ->withCount('logs')
            ->where('organization_id', $membership->organization_id)
            ->latest('id')
            ->get()
            ->map(fn (AutomationRule $rule): array => $this->summary($rule));

        return Inertia::render('Automations/Index', [
            'rules' => $rules,
            'presets' => collect(AutomationPresets::all())
                ->map(fn (array $preset, string $key): array => [
                    'value' => $key,
                    'label' => $preset['name'],
                    'trigger' => $preset['trigger'],
                    'trigger_label' => AutomationRule::triggerLabels()[$preset['trigger']] ?? $preset['trigger'],
                ])
                ->values(),
            'options' => [
                'task_templates' => TaskTemplate::query()
                    ->where('organization_id', $membership->organization_id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (TaskTemplate $template): array => [
                        'value' => $template->id,
                        'label' => $template->name,
                    ]),
                'message_templates' => MessageTemplate::query()
                    ->where('organization_id', $membership->organization_id)
                    ->where('is_active', true)
                    ->whereIn('channel', [MessageTemplate::CHANNEL_EMAIL, MessageTemplate::CHANNEL_PORTAL])
                    ->orderBy('name')
                    ->get(['id', 'name', 'channel'])
                    ->map(fn (MessageTemplate $template): array => [
                        'value' => $template->id,
                        'label' => $template->name,
                    ]),
            ],
            'can' => [
                'manage' => true,
            ],
        ]);
    }

    public function store(
        StoreAutomationRuleRequest $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);
        $planLimitChecker->assertFeature($membership->organization, 'automations');

        $data = $request->validated();
        $preset = AutomationPresets::get($data['preset_key']);
        $actions = $preset['actions'];

        if ($data['preset_key'] === 'client_created_tasks') {
            abort_unless(! empty($data['task_template_id']), HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

            $template = TaskTemplate::query()->findOrFail($data['task_template_id']);
            abort_unless($template->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);

            $actions[0]['params']['task_template_id'] = $template->id;
        }

        if ($data['preset_key'] === 'receivable_overdue_email') {
            abort_unless(! empty($data['message_template_id']), HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

            $template = MessageTemplate::query()->findOrFail($data['message_template_id']);
            abort_unless($template->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
            abort_unless(in_array($template->channel, [MessageTemplate::CHANNEL_EMAIL, MessageTemplate::CHANNEL_PORTAL], true), HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

            $actions[0]['params']['message_template_id'] = $template->id;
        }

        $rule = AutomationRule::query()->create([
            'organization_id' => $membership->organization_id,
            'name' => $data['name'] ?? $preset['name'],
            'trigger' => $preset['trigger'],
            'preset_key' => $data['preset_key'],
            'conditions' => $preset['conditions'],
            'actions' => $actions,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $auditLog->execute('web.automation_rule.created', $request->user(), $membership->organization, $rule, request: $request);

        return redirect()->route('automations.show', $rule)->with('status', 'Automação criada.');
    }

    public function show(
        AutomationRule $automationRule,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
    ): Response|RedirectResponse {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização.');
        }

        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);
        abort_unless($automationRule->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'automations');

        $logs = $automationRule->logs()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AutomationLog $log): array => [
                'id' => $log->id,
                'trigger' => $log->trigger,
                'status' => $log->status,
                'dedupe_key' => $log->dedupe_key,
                'ran_at' => DisplayFormat::dateTime($log->ran_at),
                'result' => $log->result,
            ]);

        return Inertia::render('Automations/Show', [
            'rule' => [
                ...$this->summary($automationRule),
                'conditions' => $automationRule->conditions,
                'actions' => $automationRule->actions,
            ],
            'logs' => $logs,
            'can' => [
                'manage' => $membership->isAdmin() || $membership->isManager(),
            ],
        ]);
    }

    public function pause(
        AutomationRule $automationRule,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->manageMembership($request, $webOrganizationContext, $automationRule, $planLimitChecker);

        $automationRule->update(['is_active' => false]);
        $auditLog->execute('web.automation_rule.paused', $request->user(), $membership->organization, $automationRule, request: $request);

        return back()->with('status', 'Automação pausada.');
    }

    public function resume(
        AutomationRule $automationRule,
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        PlanLimitChecker $planLimitChecker,
        RecordAuditLog $auditLog,
    ): RedirectResponse {
        $membership = $this->manageMembership($request, $webOrganizationContext, $automationRule, $planLimitChecker);

        $automationRule->update(['is_active' => true]);
        $auditLog->execute('web.automation_rule.resumed', $request->user(), $membership->organization, $automationRule, request: $request);

        return back()->with('status', 'Automação reativada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(AutomationRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'trigger' => $rule->trigger,
            'trigger_label' => AutomationRule::triggerLabels()[$rule->trigger] ?? $rule->trigger,
            'preset_key' => $rule->preset_key,
            'is_active' => $rule->is_active,
            'logs_count' => $rule->logs_count ?? $rule->logs()->count(),
            'href' => route('automations.show', $rule, absolute: false),
        ];
    }

    private function manageMembership(
        Request $request,
        WebOrganizationContext $webOrganizationContext,
        AutomationRule $automation,
        PlanLimitChecker $planLimitChecker,
    ): OrganizationMember {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership, HttpResponse::HTTP_NOT_FOUND);
        abort_unless($membership->isAdmin() || $membership->isManager(), HttpResponse::HTTP_FORBIDDEN);
        abort_unless($automation->organization_id === $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        $planLimitChecker->assertFeature($membership->organization, 'automations');

        return $membership;
    }
}
