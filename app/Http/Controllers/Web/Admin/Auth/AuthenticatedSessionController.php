<?php

namespace App\Http\Controllers\Web\Admin\Auth;

use App\Actions\Platform\RecordPlatformAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthArea;
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
        return Inertia::render('Admin/Auth/Login');
    }

    public function store(LoginRequest $request, RecordPlatformAuditLog $auditLog): RedirectResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! $user->isPlatformAdmin() || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Auth::guard('admin')->login($user, (bool) ($data['remember'] ?? false));
        Auth::shouldUse('admin');
        $request->session()->regenerate();

        $auditLog->execute(
            action: 'platform.auth.login',
            platformAdmin: $user,
            request: $request,
        );

        return AuthArea::intendedWithin($request, '/admin', route('admin.dashboard', absolute: false));
    }

    public function destroy(Request $request, RecordPlatformAuditLog $auditLog): RedirectResponse
    {
        $admin = $request->user('admin');

        if ($admin) {
            $auditLog->execute(
                action: 'platform.auth.logout',
                platformAdmin: $admin,
                request: $request,
            );
        }

        Auth::guard('admin')->logout();
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
