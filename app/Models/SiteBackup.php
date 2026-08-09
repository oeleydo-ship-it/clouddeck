<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SiteBackup extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getSizeForHumansAttribute(): string
    {
        if (! $this->size) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $size < 10 && $unit > 0 ? 1 : 0).' '.$units[$unit];
    }

    public function isFullApp(): bool
    {
        return ($this->kind ?? 'wordpress_local') === 'full_app';
    }

    public function isOffloaded(): bool
    {
        return $this->isFullApp() && filled($this->disk_path);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['ready', 'completed'], true);
    }
}
