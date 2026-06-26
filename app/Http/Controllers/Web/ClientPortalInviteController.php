<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalAccess;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ClientPortalInviteController extends Controller
{
    public function show(string $token): RedirectResponse
    {
        $access = ClientPortalAccess::findUsableByToken($token);
        abort_unless($access, HttpResponse::HTTP_NOT_FOUND);

        if ($access->hasCompletedOnboarding()) {
            return redirect()
                ->route('portal.login')
                ->with('status', 'Você já configurou seu acesso. Entre com seu e-mail e senha.');
        }

        session(['portal_invite_token' => $token]);

        return redirect()->route('client-portal.onboarding');
    }

    public function legacy(string $token): RedirectResponse
    {
        return redirect()->route('client-portal.invite', ['token' => $token]);
    }
}
