<?php

declare(strict_types=1);

namespace HoneypotPlus\Jobs;

use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class UnbanFromCloudflare implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public HoneypotPlusAttack $attack,
    ) {}

    public function handle(): void
    {
        if (! $this->attack->cf_rule_id) {
            return;
        }

        $apiToken = config('honeypot-plus.cloudflare_api_token');
        $zoneId = config('honeypot-plus.cloudflare_zone_id');

        if (empty($apiToken) || empty($zoneId)) {
            Log::warning('[HoneypotPlus] Cloudflare credentials not configured, skipping unban');

            return;
        }

        $cfRuleId = $this->attack->cf_rule_id;

        try {
            $response = Http::withToken($apiToken)
                ->delete("https://api.cloudflare.com/client/v4/zones/{$zoneId}/firewall/access_rules/rules/{$cfRuleId}");

            if ($response->successful()) {
                $this->attack->update([
                    'cf_rule_id' => null,
                    'is_blocked' => false,
                ]);

                Log::info('[HoneypotPlus] IP unbanned via Cloudflare', [
                    'ip' => $this->attack->ip,
                    'rule_id' => $cfRuleId,
                ]);
            } else {
                Log::error('[HoneypotPlus] Failed to unban via Cloudflare', [
                    'ip' => $this->attack->ip,
                    'rule_id' => $cfRuleId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($this->attempts() < $this->tries) {
                    $this->release(60 * $this->attempts());
                }
            }
        } catch (\Exception $e) {
            Log::error('[HoneypotPlus] Exception while unbanning via Cloudflare', [
                'ip' => $this->attack->ip,
                'rule_id' => $cfRuleId,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(60 * $this->attempts());
            }
        }
    }
}
