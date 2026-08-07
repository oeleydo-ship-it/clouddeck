<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_pending_invitation_role(): void
    {
        [$owner, $team, $invitation] = $this->pendingInvitation();

        $this->actingAs($owner)->patch("/teams/{$team->id}/invitations/{$invitation->id}", [
            'role' => 'admin',
        ])->assertSessionHas('status', 'Invitation role updated.');

        $this->assertSame('admin', $invitation->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'team.invitation_role_updated']);
    }

    public function test_owner_can_resend_invitation_and_reset_expiry(): void
    {
        Notification::fake();
        [$owner, $team, $invitation] = $this->pendingInvitation(expiresAt: now()->addDay());
        $oldHash = $invitation->token_hash;

        $this->actingAs($owner)->post("/teams/{$team->id}/invitations/{$invitation->id}/resend")
            ->assertSessionHas('status');

        $invitation->refresh();
        $this->assertNotSame($oldHash, $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->greaterThan(now()->addDays(6)));
        Notification::assertSentOnDemand(TeamInvitationNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'team.invitation_resent']);
    }

    public function test_resend_is_throttled_per_invitation(): void
    {
        Notification::fake();
        [$owner, $team, $invitation] = $this->pendingInvitation();

        $this->actingAs($owner)->post("/teams/{$team->id}/invitations/{$invitation->id}/resend")
            ->assertSessionHas('status');
        $this->actingAs($owner)->post("/teams/{$team->id}/invitations/{$invitation->id}/resend")
            ->assertSessionHasErrors('invitation');
    }

    public function test_owner_can_cancel_pending_invitation(): void
    {
        [$owner, $team, $invitation] = $this->pendingInvitation();

        $this->actingAs($owner)->delete("/teams/{$team->id}/invitations/{$invitation->id}")
            ->assertSessionHas('status', 'Invitation cancelled.');

        $this->assertDatabaseMissing('team_invitations', ['id' => $invitation->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'team.invitation_cancelled']);
    }

    public function test_operator_cannot_manage_invitations(): void
    {
        [$owner, $team, $invitation] = $this->pendingInvitation();
        $operator = User::factory()->create(['email_verified_at' => now()]);
        $team->memberships()->create(['user_id' => $operator->id, 'role' => 'operator', 'accepted_at' => now()]);

        $this->actingAs($operator)->patch("/teams/{$team->id}/invitations/{$invitation->id}", ['role' => 'viewer'])->assertForbidden();
        $this->actingAs($operator)->post("/teams/{$team->id}/invitations/{$invitation->id}/resend")->assertForbidden();
        $this->actingAs($operator)->delete("/teams/{$team->id}/invitations/{$invitation->id}")->assertForbidden();
        $this->assertDatabaseHas('team_invitations', ['id' => $invitation->id]);
    }

    public function test_outsider_cannot_manage_invitations(): void
    {
        [, $team, $invitation] = $this->pendingInvitation();
        $outsider = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($outsider)->delete("/teams/{$team->id}/invitations/{$invitation->id}")->assertForbidden();
    }

    public function test_admin_member_can_cancel_invitation(): void
    {
        [$owner, $team, $invitation] = $this->pendingInvitation();
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $team->memberships()->create(['user_id' => $admin->id, 'role' => 'admin', 'accepted_at' => now()]);

        $this->actingAs($admin)->delete("/teams/{$team->id}/invitations/{$invitation->id}")
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('team_invitations', ['id' => $invitation->id]);
    }

    public function test_teams_page_shows_invitation_actions(): void
    {
        [$owner, $team, $invitation] = $this->pendingInvitation(email: 'pinoycurl@gmail.com', role: 'operator');

        $this->actingAs($owner)->get('/teams')
            ->assertOk()
            ->assertSee('pinoycurl@gmail.com')
            ->assertSee('Pending invitations')
            ->assertSee('Edit')
            ->assertSee('Resend')
            ->assertSee('Delete')
            ->assertSee('What each role can do')
            ->assertSee(route('teams.invitations.resend', [$team, $invitation], false), false);
    }

    private function pendingInvitation(?\DateTimeInterface $expiresAt = null, string $email = 'invitee@example.com', string $role = 'operator'): array
    {
        RateLimiter::clear('team-invitation-resend');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::create(['owner_id' => $owner->id, 'name' => 'Developer', 'slug' => 'developer-'.Str::lower(Str::random(6))]);
        $team->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'accepted_at' => now()]);
        $invitation = $team->invitations()->create([
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => $expiresAt ?? now()->addDays(7),
            'accepted_at' => null,
        ]);
        RateLimiter::clear('team-invitation-resend:'.$invitation->id);

        return [$owner, $team, $invitation];
    }
}
