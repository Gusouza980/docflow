<?php

namespace App\Actions\Crm;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Lead;
use App\Models\OrganizationMember;
use App\Support\Billing\PlanLimitChecker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConvertLeadToClient
{
    public function __construct(private PlanLimitChecker $planLimitChecker) {}

    public function execute(Lead $lead, OrganizationMember $actor, bool $startOnboarding = false): Client
    {
        if ($lead->isConverted() && $lead->client_id !== null) {
            return $lead->client()->firstOrFail();
        }

        return DB::transaction(function () use ($lead, $actor, $startOnboarding): Client {
            $this->planLimitChecker->assertWithinLimit($lead->organization, 'max_clients', 1);

            $client = Client::query()->create([
                'organization_id' => $lead->organization_id,
                'primary_responsible_member_id' => $actor->id,
                'type' => Client::TYPE_INDIVIDUAL,
                'display_name' => $lead->name,
                'status' => Client::STATUS_ACTIVE,
                'origin' => $lead->origin,
                'potential_revenue_cents' => $lead->estimated_value_cents,
                'entered_at' => now()->toDateString(),
                'internal_notes' => $lead->service_interest
                    ? 'Convertido do CRM. Interesse: '.$lead->service_interest
                    : 'Convertido do CRM.',
            ]);

            if ($lead->email || $lead->phone) {
                ClientContact::query()->create([
                    'organization_id' => $lead->organization_id,
                    'client_id' => $client->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'is_primary' => true,
                ]);
            }

            $lead->update([
                'client_id' => $client->id,
                'stage' => Lead::STAGE_WON,
                'converted_at' => now(),
                'lost_reason' => null,
            ]);

            if ($startOnboarding) {
                $template = $lead->organization->onboardingTemplates()
                    ->where('is_active', true)
                    ->with('items')
                    ->latest('id')
                    ->first();

                if ($template === null) {
                    throw new InvalidArgumentException('Nenhum template de onboarding ativo encontrado.');
                }

                app(StartClientOnboarding::class)->execute(
                    client: $client,
                    template: $template,
                    actorUserId: $actor->user_id,
                    assignedMemberId: $actor->id,
                );
            }

            return $client->fresh();
        });
    }
}
