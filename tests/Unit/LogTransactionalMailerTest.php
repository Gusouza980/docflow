<?php

namespace Tests\Unit;

use App\Mail\LogTransactionalMailer;
use App\Models\Client;
use App\Models\ClientPortalAccess;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\PortalClientAlertNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogTransactionalMailerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_log_mailer_records_notification_without_sending(): void
    {
        Log::spy();

        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $client = Client::factory()->create(['organization_id' => $organization->id]);
        $access = ClientPortalAccess::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'token_hash' => ClientPortalAccess::makeToken()['hash'],
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $mailer = new LogTransactionalMailer;
        $mailer->notify($access, new PortalClientAlertNotification(
            subject: 'Teste',
            message: 'Mensagem de teste',
            actionUrl: 'https://example.com/portal',
        ));

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'E-mail transacional registrado')
                    && $context['notification'] === PortalClientAlertNotification::class;
            });
    }
}
