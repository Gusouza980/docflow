<?php

namespace App\Actions\Crm;

use App\Automations\AutomationRunner;
use App\Models\AutomationRule;
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
        return DB::transaction(function () use ($lead, $actor, $startOnboarding): Client {
            $lockedLead = Lead::query()
                ->whereKey($lead->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLead->converted_at !== null && $lockedLead->client_id === null) {
                throw new InvalidArgumentException('Este lead já foi convertido e não possui cliente vinculado.');
            }

            $wasCreated = false;

            if ($lockedLead->client_id !== null) {
                $client = $lockedLead->client()->firstOrFail();
            } else {
                $this->planLimitChecker->assertWithinLimit($lockedLead->organization, 'max_clients', 1);

                $client = Client::query()->create([
                    'organization_id' => $lockedLead->organization_id,
                    'primary_responsible_member_id' => $actor->id,
                    'type' => Client::TYPE_INDIVIDUAL,
                    'display_name' => $lockedLead->name,
                    'status' => Client::STATUS_ACTIVE,
                    'origin' => $lockedLead->origin,
                    'potential_revenue_cents' => $lockedLead->estimated_value_cents,
                    'entered_at' => now()->toDateString(),
                    'internal_notes' => $lockedLead->service_interest
                        ? 'Convertido do CRM. Interesse: '.$lockedLead->service_interest
                        : 'Convertido do CRM.',
                ]);
                $wasCreated = true;

                if ($lockedLead->email || $lockedLead->phone) {
                    ClientContact::query()->create([
                        'organization_id' => $lockedLead->organization_id,
                        'client_id' => $client->id,
                        'name' => $lockedLead->name,
                        'email' => $lockedLead->email,
                        'phone' => $lockedLead->phone,
                        'is_primary' => true,
                    ]);
                }

                $lockedLead->update([
                    'client_id' => $client->id,
                    'stage' => Lead::STAGE_WON,
                    'converted_at' => now(),
                    'lost_reason' => null,
                ]);
            }

            if ($startOnboarding) {
                $template = $lockedLead->organization->onboardingTemplates()
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

            $freshClient = $client->fresh();

            if ($wasCreated) {
                DB::afterCommit(function () use ($freshClient, $actor): void {
                    app(AutomationRunner::class)->dispatch(
                        organization: $freshClient->organization,
                        trigger: AutomationRule::TRIGGER_CLIENT_CREATED,
                        subject: $freshClient,
                        context: [
                            'assigned_to_member_id' => $actor->id,
                            'source' => 'lead_conversion',
                        ],
                    );
                });
            }

            return $freshClient;
        });
    }
}
