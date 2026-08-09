<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserImpersonationSession;
use App\Services\ImpersonationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminImpersonationController extends Controller
{
    public function show(Request $request, User $user): View
    {
        $historyQuery = UserImpersonationSession::query()
            ->with('admin')
            ->where('target_user_id', $user->id)
            ->latest('started_at');

        if ($request->filled('admin_id')) {
            $historyQuery->where('admin_user_id', $request->integer('admin_id'));
        }
        if ($request->filled('status')) {
            $historyQuery->where('status', $request->string('status'));
        }
        if ($request->filled('from')) {
            $historyQuery->whereDate('started_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $historyQuery->whereDate('started_at', '<=', $request->date('to'));
        }

        $activeImpersonation = UserImpersonationSession::query()
            ->with('admin')
            ->where('target_user_id', $user->id)
            ->where('status', UserImpersonationSession::STATUS_ACTIVE)
            ->whereNull('ended_at')
            ->first();

        $activity = AuditLog::query()
            ->with('actor')
            ->where(function ($query) use ($user) {
                $query->where('actor_id', $user->id)
                    ->orWhere(function ($nested) use ($user) {
                        $nested->where('auditable_type', $user->getMorphClass())
                            ->where('auditable_id', $user->getKey());
                    });
            })
            ->latest()
            ->limit(40)
            ->get();

        return view('admin.users.show', [
            'user' => $user->load('currentSubscription.plan'),
            'plans' => \App\Models\Plan::orderBy('sort_order')->get(),
            'impersonationHistory' => $historyQuery->paginate(20)->withQueryString(),
            'historyAdmins' => User::query()
                ->whereIn('id', UserImpersonationSession::query()->where('target_user_id', $user->id)->select('admin_user_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'activeImpersonation' => $activeImpersonation,
            'activity' => $activity,
            'canImpersonate' => $request->user()?->can('users.impersonate') ?? false,
            'canImpersonateAdmins' => $request->user()?->can('users.impersonate_admins') ?? false,
        ]);
    }

    public function start(Request $request, User $user, ImpersonationManager $impersonation): RedirectResponse
    {
        $this->authorize('users.impersonate');

        $data = $request->validate([
            'support_mode' => ['required', Rule::in([
                UserImpersonationSession::MODE_FULL,
                UserImpersonationSession::MODE_READ_ONLY,
            ])],
        ]);

        $impersonation->start($request, $request->user(), $user, $data['support_mode']);

        return redirect()->route('dashboard')->with('status', 'You are now viewing the console as '.$user->name.'.');
    }
}
