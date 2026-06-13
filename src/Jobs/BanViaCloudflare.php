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

final class BanViaCloudflare implements ShouldQueue
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
        if ($this->attack->is_blocked && $this->attack->cf_rule_id) {
            return;
        }

        $apiToken = config('honeypot-plus.cloudflare_api_token');
        $zoneId = config('honeypot-plus.cloudflare_zone_id');

        if (empty($apiToken) || empty($zoneId)) {
            Log::warning('[HoneypotPlus] Cloudflare credentials not configured, skipping ban');

            return;
        }

        $category = config('honeypot-plus.block_category', 'honeypot-probe');

        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/firewall/access_rules/rules", [
                    'mode' => 'block',
                    'configuration' => [
                        'target' => 'ip',
                        'value' => $this->attack->ip,
                    ],
                    'notes' => "[Laravel HoneypotPlus] Banned IP via {$category}",
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $ruleId = $data['result']['id'] ?? null;

                if ($ruleId) {
                    $this->attack->update([
                        'cf_rule_id' => $ruleId,
                        'is_blocked' => true,
                    ]);

                    Log::info('[HoneypotPlus] IP banned via Cloudflare', [
                        'ip' => $this->attack->ip,
                        'rule_id' => $ruleId,
                    ]);
                }
            } else {
                Log::error('[HoneypotPlus] Failed to ban via Cloudflare', [
                    'ip' => $this->attack->ip,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if ($this->attempts() < $this->tries) {
                    $this->release(60 * $this->attempts());
                }
            }
        } catch (\Exception $e) {
            Log::error('[HoneypotPlus] Exception while banning via Cloudflare', [
                'ip' => $this->attack->ip,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(60 * $this->attempts());
            }
        }
    }
}
