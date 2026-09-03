<?php

namespace App\Http\Controllers;

use App\Services\SystemSettings;
use App\Support\DocumentationCatalog;
use Inertia\Inertia;
use Inertia\Response;

class DocumentationController extends Controller
{
    public function __invoke(SystemSettings $settings): Response
    {
        $managedServersEnabled = $settings->managedServersEnabled();
        $stagingSitesEnabled = $settings->stagingSitesEnabled();

        return Inertia::render('Docs/Index', [
            'title' => 'Documentation',
            'sections' => DocumentationCatalog::sections($managedServersEnabled, $stagingSitesEnabled),
        ]);
    }
}
