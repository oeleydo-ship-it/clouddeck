<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserImpersonationSession;
use App\Services\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Temporary support access into a customer's console without touching their credentials.
 * The admin id lives in the session until exit or logout restores or clears it.
 */
final class ImpersonationManager
{
    public const SESSION_ADMIN_ID = 'impersonator_id';

    public const SESSION_RECORD_ID = 'impersonation_session_id';

    public const SESSION_MODE = 'impersonation_support_mode';

    public const SESSION_RETURN_TO = 'impersonation_return_to';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SystemSettings $settings,
    ) {}

    public function isImpersonating(?Request $request = null): bool
    {
        $request ??= request();

        return filled($request->session()->get(self::SESSION_ADMIN_ID))
            && filled($request->session()->get(self::SESSION_RECORD_ID));
    }

    public function adminId(?Request $request = null): ?int
    {
        $request ??= request();
        $id = $request->session()->get(self::SESSION_ADMIN_ID);

        return $id !== null ? (int) $id : null;
    }

    public function supportMode(?Request $request = null): ?string
    {
        $request ??= request();

        return $request->session()->get(self::SESSION_MODE);
    }

    public function isReadOnly(?Request $request = null): bool
    {
        return $this->supportMode($request) === UserImpersonationSession::MODE_READ_ONLY;
    }

    public function activeSession(?Request $request = null): ?UserImpersonationSession
    {
        $request ??= request();
        $id = $request->session()->get(self::SESSION_RECORD_ID);
        if (! $id) {
            return null;
        }

        return UserImpersonationSession::query()->find($id);
    }

    public function impersonator(?Request $request = null): ?User
    {
        $id = $this->adminId($request);

        return $id ? User::query()->find($id) : null;
    }

    /**
     * Metadata stamped onto audits while support access is active.
     *
     * @return array<string, mixed>
     */
    public function auditContext(?Request $request = null): array
    {
        if (! $this->isImpersonating($request)) {
            return [];
        }

        return [
            'impersonator_id' => $this->adminId($request),
            'impersonation_session_id' => $request->session()->get(self::SESSION_RECORD_ID),
            'impersonation_support_mode' => $this->supportMode($request),
        ];
    }

    public function assertCanStart(User $admin, User $target): void
    {
        if (! $admin->can('users.impersonate')) {
            throw new HttpException(403, 'You are not allowed to impersonate users.');
        }

        if ($this->isImpersonating()) {
            throw ValidationException::withMessages([
                'impersonate' => 'Exit the current impersonation session before starting another.',
            ]);
        }

        if ($admin->id === $target->id) {
            throw ValidationException::withMessages([
                'impersonate' => 'You cannot impersonate your own account.',
            ]);
        }

        if ($target->suspended_at) {
            throw ValidationException::withMessages([
                'impersonate' => 'Suspended accounts cannot be impersonated. Restore the account first.',
            ]);
        }

        if ($target->isSuperAdmin() && ! $admin->can('users.impersonate_admins')) {
            throw ValidationException::withMessages([
                'impersonate' => 'Impersonating another administrator requires the users.impersonate_admins permission.',
            ]);
        }

        $busy = UserImpersonationSession::query()
            ->where('target_user_id', $target->id)
            ->where('status', UserImpersonationSession::STATUS_ACTIVE)
            ->whereNull('ended_at')
            ->exists();

        if ($busy) {
            throw ValidationException::withMessages([
                'impersonate' => 'Another administrator is already impersonating this account.',
            ]);
        }
    }

    public function start(Request $request, User $admin, User $target, string $supportMode = UserImpersonationSession::MODE_FULL): UserImpersonationSession
    {
        $supportMode = $supportMode === UserImpersonationSession::MODE_READ_ONLY
            ? UserImpersonationSession::MODE_READ_ONLY
            : UserImpersonationSession::MODE_FULL;

        $this->assertCanStart($admin, $target);

        return DB::transaction(function () use ($request, $admin, $target, $supportMode) {
            $record = UserImpersonationSession::query()->create([
                'admin_user_id' => $admin->id,
                'target_user_id' => $target->id,
                'support_mode' => $supportMode,
                'started_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'session_identifier' => $request->session()->getId(),
                'status' => UserImpersonationSession::STATUS_ACTIVE,
            ]);

            $this->audit->record(
                $request,
                'impersonation.started',
                $target,
                [],
                [
                    'target_user_id' => $target->id,
                    'support_mode' => $supportMode,
                    'session_id' => $record->id,
                ],
                [
                    'admin_user_id' => $admin->id,
                    'admin_name' => $admin->name,
                    'target_name' => $target->name,
                    'target_email' => $target->email,
                ],
                $admin->id,
            );

            $returnTo = $request->headers->get('referer') ?: route('admin.users.show', $target);

            $request->session()->put([
                self::SESSION_ADMIN_ID => $admin->id,
                self::SESSION_RECORD_ID => $record->id,
                self::SESSION_MODE => $supportMode,
                self::SESSION_RETURN_TO => $returnTo,
            ]);

            Auth::login($target);
            $request->session()->regenerate();

            $record->update(['session_identifier' => $request->session()->getId()]);

            return $record->fresh();
        });
    }

    /**
     * Restore the original administrator. Returns the admin user, or null if the
     * impersonation session was invalid and the request was logged out instead.
     */
    public function stop(Request $request, string $status = UserImpersonationSession::STATUS_ENDED): ?User
    {
        if (! $this->isImpersonating($request)) {
            return null;
        }

        $adminId = $this->adminId($request);
        $recordId = $request->session()->get(self::SESSION_RECORD_ID);
        $returnTo = $request->session()->get(self::SESSION_RETURN_TO);
        $record = $recordId ? UserImpersonationSession::query()->find($recordId) : null;
        $admin = $adminId ? User::query()->find($adminId) : null;

        if ($record && $record->isActive()) {
            $record->update([
                'ended_at' => now(),
                'status' => $status,
            ]);
        }

        if ($admin && $record) {
            $this->audit->record(
                $request,
                'impersonation.ended',
                $record->target,
                [],
                [
                    'target_user_id' => $record->target_user_id,
                    'support_mode' => $record->support_mode,
                    'session_id' => $record->id,
                    'status' => $status,
                    'duration_seconds' => $record->fresh()->durationSeconds(),
                ],
                [
                    'admin_user_id' => $admin->id,
                    'admin_name' => $admin->name,
                ],
                $admin->id,
            );
        }

        $this->forgetSessionKeys($request);

        if (! $admin || $admin->suspended_at) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return null;
        }

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->forget([
            self::SESSION_ADMIN_ID,
            self::SESSION_RECORD_ID,
            self::SESSION_MODE,
            self::SESSION_RETURN_TO,
        ]);

        if ($returnTo) {
            $request->session()->flash('impersonation_return_to', $returnTo);
        }

        return $admin;
    }

    /**
     * End the DB record without restoring the admin (used on full logout).
     */
    public function terminateOnLogout(Request $request): void
    {
        if (! $this->isImpersonating($request)) {
            return;
        }

        $adminId = $this->adminId($request);
        $recordId = $request->session()->get(self::SESSION_RECORD_ID);
        $record = $recordId ? UserImpersonationSession::query()->find($recordId) : null;

        if ($record && $record->isActive()) {
            $record->update([
                'ended_at' => now(),
                'status' => UserImpersonationSession::STATUS_TERMINATED,
            ]);

            $this->audit->record(
                $request,
                'impersonation.ended',
                $record->target,
                [],
                [
                    'target_user_id' => $record->target_user_id,
                    'session_id' => $record->id,
                    'status' => UserImpersonationSession::STATUS_TERMINATED,
                    'reason' => 'logout',
                ],
                ['admin_user_id' => $adminId],
                $adminId,
            );
        }

        $this->forgetSessionKeys($request);
    }

    /**
     * Drop a stale/invalid impersonation session (admin gone, record closed elsewhere).
     */
    public function abandon(Request $request): void
    {
        $this->forgetSessionKeys($request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function allowImpersonateAdmins(): bool
    {
        return $this->settings->boolean('allow_impersonate_admins', false);
    }

    private function forgetSessionKeys(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_ADMIN_ID,
            self::SESSION_RECORD_ID,
            self::SESSION_MODE,
            self::SESSION_RETURN_TO,
        ]);
    }
}
