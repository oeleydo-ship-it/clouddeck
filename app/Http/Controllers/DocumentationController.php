<?php

namespace App\Http\Controllers;

use App\Services\SystemSettings;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function __invoke(SystemSettings $settings): View
    {
        return view('docs.index', [
            'title' => 'Documentation',
            'managedServersEnabled' => $settings->managedServersEnabled(),
            'stagingSitesEnabled' => $settings->stagingSitesEnabled(),
        ]);
    }
}
