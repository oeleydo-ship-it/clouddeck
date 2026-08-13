<?php

namespace App\Http\Controllers;

use App\Models\NotificationChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationSettingsController extends Controller
{
    public function index(Request $request, IncidentController $incidents)
    {
        $tabKeys = ['incidents', 'email'];
        $tab = in_array($request->query('tab'), $tabKeys, true)
            ? $request->query('tab')
            : 'incidents';
        $inbox = $incidents->listData($request);
        $channels = $request->user()->notificationChannels()->latest()->get()->map(fn (NotificationChannel $channel) => [
            'id' => $channel->id,
            'name' => $channel->name,
            'address' => $channel->configuration['address'] ?? $request->user()->email,
            'events' => $channel->events ?? [],
            'event_labels' => collect($channel->events ?? [])->map(fn ($event) => NotificationChannel::EVENTS[$event] ?? $event)->values()->all(),
        ])->values();

        $incidentEmpty = collect($inbox['incidents'] ?? [])->isEmpty();

        return Inertia::render('Notifications/Index', array_merge(
            $inbox,
            [
                'title' => 'Notifications',
                'tabs' => ['incidents' => 'Incidents', 'email' => 'Email recipients'],
                'emptyIncidents' => $incidentEmpty
                    ? ((($inbox['filters']['status'] ?? 'open') === 'open') ? 'No open incidents' : 'No incidents match these filters')
                    : null,
                'emptyRecipients' => $channels->isEmpty() ? 'No recipients yet' : null,
                'notificationChannels' => $channels,
                'notificationEvents' => NotificationChannel::EVENTS,
                'notificationTab' => $tab,
                'notificationTabKeys' => $tabKeys,
            ],
        ));
    }

    public function markRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return $this->markReadResponse($request);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->markReadResponse($request);
    }

    private function markReadResponse(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
