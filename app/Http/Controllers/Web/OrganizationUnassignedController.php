<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationUnassignedController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Organizations/Unassigned');
    }
}
