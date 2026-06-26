<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CompletePortalOnboardingRequest;
use App\Models\ClientPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ClientPortalOnboardingController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $access = $this->inviteAccess();

        if ($access->hasCompletedOnboarding()) {
            return redirect()->route('portal.login');
        }

        return Inertia::render('Portal/Onboarding', [
            'client' => [
                'name' => $access->client->display_name,
            ],
            'contact' => [
                'name' => $access->name,
                'email' => $access->email,
            ],
            'organization' => [
                'name' => $access->organization->name,
            ],
        ]);
    }

    public function store(CompletePortalOnboardingRequest $request): RedirectResponse
    {
        $access = $this->inviteAccess();

        if ($access->hasCompletedOnboarding()) {
            return redirect()->route('client-portal.dashboard');
        }

        $access->completeOnboarding($request->validated('password'));
        $access->update(['last_used_at' => now()]);

        session()->forget('portal_invite_token');

        Auth::guard('portal')->login($access);
        $request->session()->regenerate();

        return redirect()
            ->route('client-portal.dashboard')
            ->with('status', 'Acesso configurado com sucesso. Bem-vindo ao portal!');
    }

    private function inviteAccess(): ClientPortalAccess
    {
        $token = session('portal_invite_token');

        abort_unless(is_string($token) && $token !== '', HttpResponse::HTTP_FORBIDDEN);

        $access = ClientPortalAccess::findUsableByToken($token);
        abort_unless($access, HttpResponse::HTTP_NOT_FOUND);

        return $access;
    }
}
