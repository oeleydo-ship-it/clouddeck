<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeploymentController extends Controller
{
    public function show(Request $request, Deployment $deployment): View
    {
        $deployment->load('site.server');
        $this->authorize('view', $deployment->site);

        return view('deployments.show', ['deployment' => $deployment]);
    }
}
