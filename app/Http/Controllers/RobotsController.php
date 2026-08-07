<?php

namespace App\Http\Controllers;

use App\Services\SystemSettings;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(SystemSettings $settings): Response
    {
        return response($settings->robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
