<?php

declare(strict_types=1);

namespace HoneypotPlus\Models;

use HoneypotPlus\Database\Factories\HoneypotPlusAttackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

final class HoneypotPlusAttack extends Model
{
    use HasFactory;
    use Prunable;

    protected static function newFactory(): HoneypotPlusAttackFactory
    {
        return HoneypotPlusAttackFactory::new();
    }

    protected $fillable = [
        'ip',
        'honeypot_rule',
        'user_agent',
        'http_method',
        'path_requested',
        'reported_at',
        'cf_rule_id',
        'expiration_at',
        'is_blocked',
        'already_reported',
        'last_seen_at',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'already_reported' => 'boolean',
        'reported_at' => 'datetime',
        'expiration_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_blocked', true)
            ->where('expiration_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiration_at', '<', now());
    }

    public function scopeByIp(Builder $query, string $ip): Builder
    {
        return $query->where('ip', $ip);
    }

    public function isBanned(): bool
    {
        return $this->is_blocked && $this->expiration_at && $this->expiration_at->isFuture();
    }

    public function markAsReported(): void
    {
        $this->update([
            'reported_at' => now(),
            'already_reported' => true,
        ]);
    }

    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    public function prunable(): Builder
    {
        return self::where('expiration_at', '<', now()->subMonths(6));
    }
}
