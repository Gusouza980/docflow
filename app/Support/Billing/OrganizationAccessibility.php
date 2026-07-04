<?php

namespace App\Support\Billing;

use App\Models\Organization;
use App\Models\Subscription;

class OrganizationAccessibility
{
    public function isAccessible(Organization $organization): bool
    {
        if (! $organization->isOperational()) {
            return false;
        }

        $subscription = $this->subscriptionFor($organization);

        if ($subscription === null) {
            return true;
        }

        return $subscription->isAccessible();
    }

    public function blockReason(Organization $organization): ?string
    {
        if (! $organization->isOperational()) {
            return 'organization_suspended';
        }

        $subscription = $this->subscriptionFor($organization);

        if ($subscription === null || $subscription->isAccessible()) {
            return null;
        }

        return match ($subscription->status) {
            Subscription::STATUS_TRIALING => 'trial_expired',
            Subscription::STATUS_PAST_DUE => 'grace_expired',
            Subscription::STATUS_CANCELED => 'subscription_canceled',
            Subscription::STATUS_PAUSED => 'subscription_paused',
            default => 'subscription_inactive',
        };
    }

    public function blockMessage(?string $reason): string
    {
        return match ($reason) {
            'organization_suspended' => 'Esta organização está suspensa. Entre em contato com o suporte Docflow.',
            'trial_expired' => 'Seu período de trial expirou. Regularize a assinatura para continuar usando o Docflow.',
            'grace_expired' => 'O prazo de tolerância da assinatura expirou. Regularize o pagamento para restaurar o acesso.',
            'subscription_canceled' => 'A assinatura desta organização foi cancelada. Entre em contato com o suporte para reativar.',
            'subscription_paused' => 'A assinatura está pausada. Entre em contato com o suporte Docflow.',
            default => 'O acesso a esta organização está indisponível no momento.',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function summaryFor(Organization $organization): ?array
    {
        $subscription = $this->subscriptionFor($organization);

        if ($subscription === null) {
            return null;
        }

        $blockReason = $this->blockReason($organization);

        return [
            'status' => $subscription->status,
            'is_accessible' => $this->isAccessible($organization),
            'trial_days_left' => $subscription->daysUntilTrialEnds(),
            'on_grace_period' => $subscription->onGracePeriod(),
            'block_reason' => $blockReason,
            'block_message' => $blockReason ? $this->blockMessage($blockReason) : null,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'past_due_at' => $subscription->past_due_at?->toIso8601String(),
        ];
    }

    private function subscriptionFor(Organization $organization): ?Subscription
    {
        $organization->loadMissing('subscription');

        return $organization->subscription;
    }
}
