<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthArea
{
    public static function isAdmin(Request $request): bool
    {
        return $request->is('admin') || $request->is('admin/*');
    }

    public static function isTenant(Request $request): bool
    {
        return $request->is('plataforma') || $request->is('plataforma/*');
    }

    public static function intendedWithin(Request $request, string $prefix, string $fallback): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');
        $path = is_string($intended) ? (parse_url($intended, PHP_URL_PATH) ?: '') : '';

        if (is_string($intended) && str_starts_with($path, $prefix)) {
            return redirect($intended);
        }

        return redirect($fallback);
    }
}
