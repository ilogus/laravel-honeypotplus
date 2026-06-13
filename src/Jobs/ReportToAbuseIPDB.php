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

final class ReportToAbuseIPDB implements ShouldQueue
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
        if ($this->attack->already_reported) {
            return;
        }

        $apiKey = config('honeypot-plus.abuseipdb_key');

        if (empty($apiKey)) {
            Log::warning('[HoneypotPlus] AbuseIPDB API key not configured, skipping report');

            return;
        }

        $categories = config('honeypot-plus.abuseipdb_categories', [21, 19]);
        $maxAge = config('honeypot-plus.abuseipdb_max_age_days', 30);

        if ($this->attack->created_at->lt(now()->subDays($maxAge))) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'Key' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->asForm()
                ->post('https://api.abuseipdb.com/api/v2/report', [
                    'ip' => $this->attack->ip,
                    'categories' => implode(',', $categories),
                    'comment' => "[Laravel HoneypotPlus] Automated report - Honeypot access detected on path: {$this->attack->path_requested} via rule: {$this->attack->honeypot_rule}",
                ]);

            if ($response->successful()) {
                $this->attack->markAsReported();

                Log::info('[HoneypotPlus] IP reported to AbuseIPDB', [
                    'ip' => $this->attack->ip,
                    'response' => $response->json(),
                ]);
            } else {
                Log::error('[HoneypotPlus] Failed to report to AbuseIPDB', [
                    'ip' => $this->attack->ip,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->release(60);
            }
        } catch (\Exception $e) {
            Log::error('[HoneypotPlus] Exception while reporting to AbuseIPDB', [
                'ip' => $this->attack->ip,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(60 * $this->attempts());
            }
        }
    }
}
