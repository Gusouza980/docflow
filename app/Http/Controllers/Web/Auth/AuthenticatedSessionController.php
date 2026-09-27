<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Organizations\RecordAuditLog;
use App\Actions\Platform\StopImpersonation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthArea;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, RecordAuditLog $auditLog): RedirectResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', $data['email'])->first();

        if ($user?->isPlatformAdmin()) {
            throw ValidationException::withMessages([
                'email' => 'Use o login administrativo para acessar o painel da plataforma.',
            ]);
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Auth::guard('web')->login($user, (bool) ($data['remember'] ?? false));
        Auth::shouldUse('web');
        $request->session()->regenerate();

        $auditLog->execute('web.auth.login', $user, request: $request);

        return AuthArea::intendedWithin($request, '/plataforma', route('dashboard', absolute: false));
    }

    public function destroy(Request $request, RecordAuditLog $auditLog, Impersonation $impersonation, StopImpersonation $stopImpersonation): RedirectResponse
    {
        if ($impersonation->isActive($request)) {
            return $stopImpersonation->execute($request);
        }

        $auditLog->execute('web.auth.logout', $request->user('web'), request: $request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
