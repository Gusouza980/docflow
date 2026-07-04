<?php

namespace Tests\Feature;

use App\Actions\Notifications\NotifyPortalClient;
use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\PortalClientAlertNotification;
use App\Notifications\PortalResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalTransactionalMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_forgot_password_enqueues_notification_on_queue(): void
    {
        Queue::fake();

        [$user, $organization, $client] = $this->createContext();

        ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Maria Cliente',
            'email' => 'maria@example.com',
            'token_hash' => ClientPortalAccess::makeToken()['hash'],
            'password' => Hash::make('password123'),
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $this->post('/portal/forgot-password', ['email' => 'maria@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_reset_password_notification_contains_absolute_portal_url(): void
    {
        Notification::fake();

        [$user, $organization, $client] = $this->createContext();

        $access = ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Maria Cliente',
            'email' => 'maria@example.com',
            'token_hash' => ClientPortalAccess::makeToken()['hash'],
            'password' => Hash::make('password123'),
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $this->post('/portal/forgot-password', ['email' => 'maria@example.com']);

        Notification::assertSentTo($access, PortalResetPasswordNotification::class, function (PortalResetPasswordNotification $notification) use ($access): bool {
            $html = $notification->toMail($access)->render();

            return str_contains($html, route('portal.password.reset', [
                'token' => $notification->token,
                'email' => $access->email,
            ], absolute: true));
        });
    }

    public function test_portal_alert_notification_contains_action_url(): void
    {
        Notification::fake();

        [$user, $organization, $member] = $this->createMember(OrganizationMember::ROLE_ADMIN);
        $client = $this->createClient($organization, $member);
        $access = $this->createPortalAccess($organization, $client, $user);

        $actionUrl = route('client-portal.notifications.index', absolute: true);

        app(NotifyPortalClient::class)->execute(
            client: $client,
            subject: 'Nova mensagem',
            message: 'Você tem uma atualização no portal.',
            actionUrl: $actionUrl,
        );

        Notification::assertSentTo($access, PortalClientAlertNotification::class, function (PortalClientAlertNotification $notification) use ($access, $actionUrl): bool {
            $this->assertSame($actionUrl, $notification->actionUrl);

            $html = $notification->toMail($access)->render();

            return str_contains($html, $actionUrl)
                && str_contains($html, 'Acessar portal do cliente');
        });
    }

    /**
     * @return array{0: User, 1: Organization, 2: Client}
     */
    private function createContext(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationMember::ROLE_ADMIN,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);

        return [$user, $organization, $client];
    }

    /**
     * @return array{0: User, 1: Organization, 2: OrganizationMember}
     */
    private function createMember(string $role): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $member = OrganizationMember::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMember::STATUS_ACTIVE,
        ]);

        return [$user, $organization, $member];
    }

    private function createClient(Organization $organization, OrganizationMember $member): Client
    {
        $client = Client::factory()->create([
            'organization_id' => $organization->id,
            'primary_responsible_member_id' => $member->id,
        ]);
        $client->responsibles()->attach($member->id, ['is_primary' => true]);

        return $client;
    }

    private function createPortalAccess(Organization $organization, Client $client, User $user): ClientPortalAccess
    {
        return ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Maria Cliente',
            'email' => 'portal@example.com',
            'token_hash' => ClientPortalAccess::makeToken()['hash'],
            'password' => Hash::make('password123'),
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
    }
}
