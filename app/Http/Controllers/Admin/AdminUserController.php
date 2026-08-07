<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function suspend(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot suspend your own account.');
        $data = $request->validate(['reason' => ['required_if:suspend,1', 'nullable', 'string', 'max:1000'], 'suspend' => ['required', 'boolean']]);
        $old = ['suspended_at' => $user->suspended_at?->toIso8601String(), 'suspension_reason' => $user->suspension_reason];
        $user->update(['suspended_at' => $request->boolean('suspend') ? now() : null, 'suspension_reason' => $request->boolean('suspend') ? $data['reason'] : null]);
        if ($request->boolean('suspend')) {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $audit->record($request, $request->boolean('suspend') ? 'user.suspended' : 'user.restored', $user, $old, ['suspended_at' => $user->suspended_at?->toIso8601String(), 'reason' => $user->suspension_reason]);

        return back()->with('status', $request->boolean('suspend') ? 'User suspended and sessions revoked.' : 'User restored.');
    }

    public function role(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $role = $request->validate(['role' => ['required', Rule::in(['customer', 'super_admin'])]])['role'];
        abort_if($user->id === $request->user()->id && $role !== 'super_admin', 422, 'You cannot remove your own administrator role.');
        $old = $user->role;
        $user->forceFill(['role' => $role])->save();
        $audit->record($request, 'user.role_changed', $user, ['role' => $old], ['role' => $role]);

        return back()->with('status', 'User role updated.');
    }

    public function subscription(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')->where('active', true)], 'period_days' => ['required', 'integer', 'between:1,3660']]);
        $plan = Plan::findOrFail($data['plan_id']);
        DB::transaction(function () use ($user, $plan, $data): void {
            $user->subscriptions()->whereIn('status', ['active', 'trialing'])->update(['status' => 'ended', 'ended_at' => now()]);
            $user->subscriptions()->create(['plan_id' => $plan->id, 'status' => 'active', 'provider' => 'manual', 'current_period_ends_at' => now()->addDays((int) $data['period_days'])]);
        });
        $audit->record($request, 'subscription.assigned', $user, [], ['plan' => $plan->slug, 'period_days' => $data['period_days']]);

        return back()->with('status', 'Subscription assigned.');
    }
}
