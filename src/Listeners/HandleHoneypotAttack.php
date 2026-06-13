<?php

declare(strict_types=1);

namespace HoneypotPlus\Listeners;

use HoneypotPlus\Events\HoneypotAttackDetected;
use HoneypotPlus\Jobs\BanViaCloudflare;
use HoneypotPlus\Jobs\ReportToAbuseIPDB;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Support\Facades\Log;

final class HandleHoneypotAttack
{
    public function handle(HoneypotAttackDetected $event): void
    {
        $existingAttack = HoneypotPlusAttack::byIp($event->ip)
            ->active()
            ->first();

        if ($existingAttack) {
            $existingAttack->updateLastSeen();

            if (config('honeypot-plus.logging', true)) {
                Log::warning('[HoneypotPlus] Already banned IP tried again', [
                    'ip' => $event->ip,
                    'rule' => $event->honeypotRule,
                    'path' => $event->pathRequested,
                ]);
            }

            return;
        }

        $banDurationHours = (int) config('honeypot-plus.ban_duration_hours', 24);

        $attack = HoneypotPlusAttack::create([
            'ip' => $event->ip,
            'honeypot_rule' => $event->honeypotRule,
            'user_agent' => $event->userAgent,
            'http_method' => $event->httpMethod,
            'path_requested' => $event->pathRequested,
            'expiration_at' => now()->addHours($banDurationHours),
        ]);

        if ($this->shouldReportToAbuseIPDB()) {
            ReportToAbuseIPDB::dispatch($attack);
        }

        if ($this->shouldBanViaCloudflare()) {
            BanViaCloudflare::dispatch($attack);
        }

        if (config('honeypot-plus.logging', true)) {
            Log::warning('[HoneypotPlus] Attack detected', [
                'ip' => $event->ip,
                'rule' => $event->honeypotRule,
                'path' => $event->pathRequested,
                'method' => $event->httpMethod,
            ]);
        }
    }

    private function shouldReportToAbuseIPDB(): bool
    {
        return ! empty(config('honeypot-plus.abuseipdb_key'));
    }

    private function shouldBanViaCloudflare(): bool
    {
        return ! empty(config('honeypot-plus.cloudflare_api_token'))
            && ! empty(config('honeypot-plus.cloudflare_zone_id'));
    }
}
