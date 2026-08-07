<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\Services\AuditLogger;
use App\Services\QuotaManager;
use App\Services\TeamAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $owned = $request->user()->ownedTeams()->with([
            'memberships.user',
            'invitations' => fn ($query) => $query->whereNull('accepted_at')->latest(),
        ])->latest()->get();
        $memberships = $request->user()->teamMemberships()->with('team.owner')->whereNotNull('accepted_at')->get();

        return view('teams.index', compact('owned', 'memberships'));
    }

    public function store(Request $request, QuotaManager $quotas, AuditLogger $audit): RedirectResponse
    {
        $quotas->assertCanCreate($request->user(), 'teams');
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $team = DB::transaction(function () use ($request, $data): Team {
            $team = Team::create(['owner_id' => $request->user()->id, 'name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);
            $team->memberships()->create(['user_id' => $request->user()->id, 'role' => 'owner', 'accepted_at' => now()]);
            $request->user()->update(['current_team_id' => $team->id]);

            return $team;
        });
        $audit->record($request, 'team.created', $team, [], ['name' => $team->name]);

        return back()->with('status', 'Team created.');
    }

    public function switch(Request $request, Team $team, TeamAccess $access, AuditLogger $audit): RedirectResponse
    {
        abort_unless($access->canView($request->user(), $team), 403);
        $request->user()->update(['current_team_id' => $team->id]);
        $audit->record($request, 'team.workspace_switched', $team);

        return back()->with('status', "{$team->name} is now your active workspace.");
    }

    public function invite(Request $request, Team $team, QuotaManager $quotas, AuditLogger $audit, TeamAccess $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $team), 403);
        $quotas->assertCanCreate($request->user(), 'team_members');
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in(['admin', 'operator', 'viewer'])]]);
        abort_if(strcasecmp($data['email'], $request->user()->email) === 0, 422, 'The team owner is already a member.');
        $token = Str::random(64);
        $invitation = $team->invitations()->updateOrCreate(['email' => Str::lower($data['email'])], ['role' => $data['role'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'accepted_at' => null]);
        Notification::route('mail', $invitation->email)->notify(new TeamInvitationNotification($invitation->load('team'), $token));
        $audit->record($request, 'team.invited', $team, [], ['email' => $invitation->email, 'role' => $invitation->role]);

        return back()->with('status', 'Team invitation sent.');
    }

    public function updateInvitation(Request $request, Team $team, TeamInvitation $invitation, AuditLogger $audit, TeamAccess $access): RedirectResponse
    {
        $this->assertManagesPendingInvitation($request, $team, $invitation, $access);
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'operator', 'viewer'])]]);
        $oldRole = $invitation->role;
        $invitation->update(['role' => $data['role']]);
        $audit->record($request, 'team.invitation_role_updated', $team, ['role' => $oldRole], ['role' => $invitation->role, 'email' => $invitation->email]);

        return back()->with('status', 'Invitation role updated.');
    }

    public function resendInvitation(Request $request, Team $team, TeamInvitation $invitation, AuditLogger $audit, TeamAccess $access): RedirectResponse
    {
        $this->assertManagesPendingInvitation($request, $team, $invitation, $access);

        $key = 'team-invitation-resend:'.$invitation->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'invitation' => "Please wait {$seconds} seconds before resending this invitation.",
            ]);
        }

        RateLimiter::hit($key, 180);

        $token = Str::random(64);
        $invitation->update([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);
        Notification::route('mail', $invitation->email)->notify(new TeamInvitationNotification($invitation->load('team'), $token));
        $audit->record($request, 'team.invitation_resent', $team, [], ['email' => $invitation->email, 'role' => $invitation->role]);

        return back()->with('status', 'Invitation resent. Expiry reset to seven days.');
    }

    public function destroyInvitation(Request $request, Team $team, TeamInvitation $invitation, AuditLogger $audit, TeamAccess $access): RedirectResponse
    {
        $this->assertManagesPendingInvitation($request, $team, $invitation, $access);
        $audit->record($request, 'team.invitation_cancelled', $team, ['email' => $invitation->email, 'role' => $invitation->role]);
        $invitation->delete();

        return back()->with('status', 'Invitation cancelled.');
    }

    public function accept(Request $request, TeamInvitation $teamInvitation, string $token, AuditLogger $audit): RedirectResponse
    {
        abort_unless(! $teamInvitation->accepted_at && $teamInvitation->expires_at->isFuture() && hash_equals($teamInvitation->token_hash, hash('sha256', $token)) && strcasecmp($teamInvitation->email, $request->user()->email) === 0, 403);
        DB::transaction(function () use ($request, $teamInvitation): void {
            $teamInvitation->team->memberships()->updateOrCreate(['user_id' => $request->user()->id], ['role' => $teamInvitation->role, 'accepted_at' => now()]);
            $teamInvitation->update(['accepted_at' => now()]);
            $request->user()->update(['current_team_id' => $teamInvitation->team_id]);
        });
        $audit->record($request, 'team.invitation_accepted', $teamInvitation->team, [], ['invitation_id' => $teamInvitation->id]);

        return redirect()->route('teams.index')->with('status', 'Team invitation accepted.');
    }

    private function assertManagesPendingInvitation(Request $request, Team $team, TeamInvitation $invitation, TeamAccess $access): void
    {
        abort_unless($access->canManage($request->user(), $team) && $invitation->team_id === $team->id && $invitation->isPending(), 403);
    }

    public function role(Request $request, Team $team, TeamMember $member, AuditLogger $audit, TeamAccess $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $team) && $member->team_id === $team->id && $member->role !== 'owner', 403);
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'operator', 'viewer'])]]);
        $oldRole = $member->role;
        $member->update(['role' => $data['role']]);
        $audit->record($request, 'team.member_role_updated', $team, ['role' => $oldRole], ['role' => $member->role, 'user_id' => $member->user_id]);

        return back()->with('status', 'Team role updated.');
    }

    public function remove(Request $request, Team $team, TeamMember $member, AuditLogger $audit, TeamAccess $access): RedirectResponse
    {
        abort_unless($access->canManage($request->user(), $team) && $member->team_id === $team->id && $member->role !== 'owner', 403);
        $audit->record($request, 'team.member_removed', $team, ['user_id' => $member->user_id, 'role' => $member->role]);
        $member->delete();
        User::whereKey($member->user_id)->where('current_team_id', $team->id)->update(['current_team_id' => null]);

        return back()->with('status', 'Team member removed.');
    }
}
