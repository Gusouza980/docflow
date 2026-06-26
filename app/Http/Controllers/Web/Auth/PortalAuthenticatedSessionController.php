<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\PortalLoginRequest;
use App\Models\ClientPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PortalAuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Portal/Login');
    }

    public function store(PortalLoginRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $access = ClientPortalAccess::findForPortalLogin($data['email'], $data['password']);

        if (! $access) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Auth::guard('portal')->login($access, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();

        $access->update(['last_used_at' => now()]);

        return redirect()->intended(route('client-portal.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
