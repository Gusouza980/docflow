<?php

namespace App\Http\Controllers\Web\Platform;

use App\Http\Controllers\Controller;
use App\Support\Platform\UsageGuideCatalog;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class UsageGuideController extends Controller
{
    public function index(UsageGuideCatalog $catalog): Response
    {
        return Inertia::render('Platform/Guides/Index', [
            'guides' => $catalog->index(),
        ]);
    }

    public function show(string $guide, UsageGuideCatalog $catalog): Response
    {
        try {
            $page = $catalog->find($guide);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return Inertia::render('Platform/Guides/Show', [
            'guide' => $page,
            'guides' => $catalog->index(),
        ]);
    }
}
