<?php

namespace App\Http\Controllers;

use App\Models\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function index(Request $request, IncidentController $incidents): View
    {
        $tabKeys = ['incidents', 'email'];
        $tab = in_array($request->query('tab'), $tabKeys, true)
            ? $request->query('tab')
            : 'incidents';

        return view('notifications.index', array_merge(
            $incidents->listData($request),
            [
                'notificationChannels' => $request->user()->notificationChannels()->latest()->get(),
                'notificationEvents' => NotificationChannel::EVENTS,
                'notificationTab' => $tab,
                'notificationTabKeys' => $tabKeys,
            ],
        ));
    }
}
