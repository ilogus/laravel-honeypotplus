<?php

declare(strict_types=1);

namespace HoneypotPlus;

use HoneypotPlus\Jobs\BanViaCloudflare;
use HoneypotPlus\Jobs\UnbanFromCloudflare;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Contracts\Foundation\Application;

final class HoneypotPlus
{
    public function __construct(
        private Application $app,
    ) {}

    public function isBanned(string $ip): bool
    {
        $attack = HoneypotPlusAttack::byIp($ip)->active()->first();

        return $attack?->isBanned() ?? false;
    }

    public function getBannedRecord(string $ip): ?HoneypotPlusAttack
    {
        return HoneypotPlusAttack::byIp($ip)->active()->first();
    }

    public function ban(string $ip, int $hours = 24): HoneypotPlusAttack
    {
        $existing = HoneypotPlusAttack::byIp($ip)->active()->first();

        if ($existing) {
            return $existing;
        }

        $attack = HoneypotPlusAttack::create([
            'ip' => $ip,
            'honeypot_rule' => 'manual-ban',
            'http_method' => 'MANUAL',
            'path_requested' => '/manual-ban',
            'expiration_at' => now()->addHours($hours),
        ]);

        if ($this->shouldUseCloudflare()) {
            BanViaCloudflare::dispatch($attack);
        }

        return $attack;
    }

    public function unban(string $ip): bool
    {
        $attack = HoneypotPlusAttack::byIp($ip)->active()->first();

        if (! $attack) {
            return false;
        }

        if ($attack->cf_rule_id && $this->shouldUseCloudflare()) {
            UnbanFromCloudflare::dispatch($attack);
        }

        $attack->update([
            'is_blocked' => false,
            'expiration_at' => now(),
        ]);

        return true;
    }

    public function getStats(): array
    {
        return [
            'total' => HoneypotPlusAttack::count(),
            'active' => HoneypotPlusAttack::active()->count(),
            'expired' => HoneypotPlusAttack::expired()->count(),
            'reported' => HoneypotPlusAttack::whereNotNull('reported_at')->count(),
        ];
    }

    private function shouldUseCloudflare(): bool
    {
        return ! empty(config('honeypot-plus.cloudflare_api_token'))
            && ! empty(config('honeypot-plus.cloudflare_zone_id'));
    }
}
