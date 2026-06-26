<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\PortalForgotPasswordRequest;
use App\Models\ClientPortalAccess;
use App\Notifications\PortalResetPasswordNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortalPasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Portal/ForgotPassword');
    }

    public function store(PortalForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        $accesses = ClientPortalAccess::query()
            ->where('email', $email)
            ->where('status', ClientPortalAccess::STATUS_ACTIVE)
            ->whereNotNull('password_set_at')
            ->get();

        if ($accesses->isNotEmpty()) {
            $token = Str::random(64);

            DB::table('portal_password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => hash('sha256', $token), 'created_at' => now()],
            );

            Notification::send($accesses->first(), new PortalResetPasswordNotification($token));
        }

        return back()->with('status', 'Se o e-mail existir, enviaremos as instruções de redefinição.');
    }
}
