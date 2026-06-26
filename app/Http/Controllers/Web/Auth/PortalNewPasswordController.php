<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\PortalResetPasswordRequest;
use App\Models\ClientPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PortalNewPasswordController extends Controller
{
    public function create(string $token): Response
    {
        return Inertia::render('Portal/ResetPassword', [
            'token' => $token,
            'email' => request()->string('email')->toString(),
        ]);
    }

    public function store(PortalResetPasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $record = DB::table('portal_password_reset_tokens')->where('email', $data['email'])->first();

        if (
            ! $record
            || ! hash_equals($record->token, hash('sha256', $data['token']))
            || Carbon::parse($record->created_at)->addMinutes(60)->isPast()
        ) {
            throw ValidationException::withMessages([
                'email' => ['Este link de redefinição é inválido ou expirou.'],
            ]);
        }

        $accesses = ClientPortalAccess::query()
            ->where('email', $data['email'])
            ->where('status', ClientPortalAccess::STATUS_ACTIVE)
            ->whereNotNull('password_set_at')
            ->get();

        if ($accesses->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => ['Não encontramos um acesso ativo para este e-mail.'],
            ]);
        }

        foreach ($accesses as $access) {
            $access->update([
                'password' => Hash::make($data['password']),
                'password_set_at' => now(),
            ]);
        }

        DB::table('portal_password_reset_tokens')->where('email', $data['email'])->delete();

        return redirect()->route('portal.login')->with('status', 'Senha redefinida com sucesso. Faça login para continuar.');
    }
}
