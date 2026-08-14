<?php

namespace App\Actions\Platform;

use App\Actions\Organizations\CreateOrganization;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProvisionTenant
{
    public function __construct(
        private CreateOrganization $createOrganization,
        private RecordPlatformAuditLog $recordPlatformAuditLog,
    ) {}

    /**
     * @param  array{
     *     owner_name: string,
     *     owner_email: string,
     *     name: string,
     *     document?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     timezone?: string|null,
     *     plan_id?: int|null
     * }  $data
     * @return array{user: User, organization: Organization, reset_url: string}
     */
    public function execute(User $platformAdmin, array $data, ?Request $request = null): array
    {
        $ownerEmail = mb_strtolower($data['owner_email']);

        if (User::query()->where('email', $ownerEmail)->exists()) {
            throw ValidationException::withMessages([
                'owner_email' => 'Já existe um usuário com este e-mail.',
            ]);
        }

        $plan = $this->resolvePlan($data['plan_id'] ?? null);

        return DB::transaction(function () use ($platformAdmin, $data, $ownerEmail, $plan, $request): array {
            $user = User::query()->create([
                'name' => $data['owner_name'],
                'email' => $ownerEmail,
                'password' => Str::password(32),
                'is_platform_admin' => false,
            ]);

            $organization = $this->createOrganization->execute($user, [
                'name' => $data['name'],
                'document' => $data['document'] ?? null,
                'email' => $data['email'] ?? $ownerEmail,
                'phone' => $data['phone'] ?? null,
                'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
                'plan_id' => $plan->id,
            ]);

            $this->recordPlatformAuditLog->execute(
                action: PlatformAuditLog::ACTION_TENANT_PROVISIONED,
                platformAdmin: $platformAdmin,
                subject: $organization,
                metadata: [
                    'owner_user_id' => $user->id,
                    'owner_email' => $user->email,
                    'plan_id' => $plan->id,
                ],
                request: $request,
            );

            $token = Password::broker()->createToken($user);
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]));

            $user->notify(new ResetPassword($token));

            return [
                'user' => $user,
                'organization' => $organization,
                'reset_url' => $resetUrl,
            ];
        });
    }

    private function resolvePlan(mixed $planId): Plan
    {
        if ($planId) {
            $plan = Plan::query()
                ->whereKey($planId)
                ->where('is_active', true)
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        $defaultPlan = Plan::query()
            ->where('slug', config('docflow.default_plan_slug', 'essencial'))
            ->where('is_active', true)
            ->first();

        if ($defaultPlan) {
            return $defaultPlan;
        }

        throw ValidationException::withMessages([
            'plan_id' => 'Nenhum plano ativo está disponível. Execute o seeder de planos.',
        ]);
    }
}
