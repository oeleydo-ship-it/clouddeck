<?php

namespace App\Http\Controllers;

use App\Services\SystemSettings;
use Inertia\Inertia;
use Inertia\Response;

class DocumentationController extends Controller
{
    public function __invoke(SystemSettings $settings): Response
    {
        return Inertia::render('Docs/Index', [
            'title' => 'Documentation',
            'managedServersEnabled' => $settings->managedServersEnabled(),
            'stagingSitesEnabled' => $settings->stagingSitesEnabled(),
        ]);
    }
}
