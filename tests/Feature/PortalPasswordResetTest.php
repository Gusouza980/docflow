<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\PortalResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalPasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_portal_forgot_password_sends_notification_when_access_exists(): void
    {
        Notification::fake();

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

        $this->get('/portal/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Portal/ForgotPassword', false));

        $this->post('/portal/forgot-password', ['email' => 'maria@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('portal_password_reset_tokens', [
            'email' => 'maria@example.com',
        ]);

        $access = ClientPortalAccess::query()->where('email', 'maria@example.com')->first();

        Notification::assertSentTo($access, PortalResetPasswordNotification::class);
    }

    public function test_portal_can_reset_password_with_valid_token(): void
    {
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

        $plainToken = 'test-reset-token';
        DB::table('portal_password_reset_tokens')->insert([
            'email' => 'maria@example.com',
            'token' => hash('sha256', $plainToken),
            'created_at' => now(),
        ]);

        $this->get("/portal/reset-password/{$plainToken}?email=maria@example.com")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Portal/ResetPassword', false));

        $this->post('/portal/reset-password', [
            'token' => $plainToken,
            'email' => 'maria@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('portal.login'))
            ->assertSessionHas('status');

        $access = ClientPortalAccess::query()->where('email', 'maria@example.com')->first();
        $this->assertTrue(Hash::check('new-password-123', $access->password));
        $this->assertDatabaseMissing('portal_password_reset_tokens', ['email' => 'maria@example.com']);
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
}
