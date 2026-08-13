<?php

namespace App\Http\Controllers\Web\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Platform\StorePlanRequest;
use App\Http\Requests\Web\Platform\UpdatePlanRequest;
use App\Models\Plan;
use App\Support\Billing\NormalizesPlanFeatures;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        private NormalizesPlanFeatures $normalizesPlanFeatures,
    ) {}

    public function index(): Response
    {
        $plans = Plan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Plan $plan): array => $this->planPayload($plan));

        return Inertia::render('Platform/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Platform/Plans/Form', [
            'plan' => null,
            'limitKeys' => array_keys(config('docflow.plan_limits', [])),
            'featureKeys' => array_keys(config('docflow.plan_features', [])),
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['features'] = $this->normalizesPlanFeatures->normalize(
            $data['features'] ?? [],
            $data['slug'] ?? null,
        );

        $plan = Plan::create($data);

        return redirect()
            ->route('platform.plans.edit', $plan)
            ->with('status', 'Plano criado.');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Platform/Plans/Form', [
            'plan' => $this->planPayload($plan),
            'limitKeys' => array_keys(config('docflow.plan_limits', [])),
            'featureKeys' => array_keys(config('docflow.plan_features', [])),
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();
        $data['features'] = $this->normalizesPlanFeatures->normalize(
            $data['features'] ?? [],
            $data['slug'] ?? $plan->slug,
        );

        $plan->update($data);

        return redirect()
            ->route('platform.plans.edit', $plan)
            ->with('status', 'Plano atualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'billing_interval' => $plan->billing_interval,
            'trial_days' => $plan->trial_days,
            'limits' => $plan->limits ?? [],
            'features' => $this->normalizesPlanFeatures->normalize($plan->features, $plan->slug),
            'is_public' => $plan->is_public,
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
            'organizations_count' => $plan->organizations()->count(),
        ];
    }
}
