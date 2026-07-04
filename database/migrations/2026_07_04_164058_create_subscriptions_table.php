<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->string('billing_provider', 32)->default('manual');
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('past_due_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'trial_ends_at']);
            $table->index(['status', 'past_due_at']);
        });

        $defaultPlanId = Plan::query()
            ->where('slug', config('docflow.default_plan_slug', 'essencial'))
            ->value('id');

        Organization::query()->each(function (Organization $organization) use ($defaultPlanId): void {
            if ($organization->subscription()->exists()) {
                return;
            }

            $planId = $organization->plan_id ?? $defaultPlanId;

            if (! $planId) {
                return;
            }

            $periodEnd = now()->addMonth();

            Subscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $planId,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_provider' => 'manual',
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
