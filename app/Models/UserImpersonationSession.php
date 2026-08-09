<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserImpersonationSession extends Model
{
    use HasUuid;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_TERMINATED = 'terminated';

    public const MODE_FULL = 'full';

    public const MODE_READ_ONLY = 'read_only';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->ended_at === null;
    }

    public function isReadOnly(): bool
    {
        return $this->support_mode === self::MODE_READ_ONLY;
    }

    public function durationSeconds(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        $end = $this->ended_at ?? now();

        return max(0, $this->started_at->diffInSeconds($end));
    }

    public function durationForHumans(): string
    {
        $seconds = $this->durationSeconds();
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;

        return $hours.'h'.($rem > 0 ? ' '.$rem.'m' : '');
    }
}
