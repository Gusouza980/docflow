<?php

namespace App\Http\Controllers\Web;

use App\Actions\Platform\StopImpersonation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationStopController extends Controller
{
    public function destroy(Request $request, StopImpersonation $stopImpersonation): RedirectResponse
    {
        return $stopImpersonation->execute($request);
    }
}
