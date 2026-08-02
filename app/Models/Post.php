<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasUuid, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * A post scheduled for later is not published yet, so the public list has to compare
     * against now rather than merely checking the column is set.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->isScheduled() => 'Scheduled',
            $this->isPublished() => 'Published',
            default => 'Draft',
        };
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path && Storage::disk('public')->exists($this->cover_path)
            ? Storage::disk('public')->url($this->cover_path)
            : null;
    }

    public function getReadingTimeAttribute(): int
    {
        return max(1, (int) ceil(Str::of(strip_tags($this->body))->wordCount() / 200));
    }
}
