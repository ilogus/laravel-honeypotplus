<?php

declare(strict_types=1);

namespace HoneypotPlus\Commands;

use HoneypotPlus\Jobs\UnbanFromCloudflare;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Console\Command;

final class CleanupCommand extends Command
{
    protected $signature = 'honeypot-plus:cleanup
        {--dry-run : Show what would be done without actually doing it}
        {--force : Run without confirmation}';

    protected $description = 'Clean up expired honeypot bans';

    public function handle(): int
    {
        $expiredAttacks = HoneypotPlusAttack::where('is_blocked', true)
            ->where('expiration_at', '<', now())
            ->get();

        if ($expiredAttacks->isEmpty()) {
            $this->info('No expired bans to clean up.');

            return self::SUCCESS;
        }

        $this->info("Found {$expiredAttacks->count()} expired ban(s).");

        if (! $this->option('force')) {
            $this->table(
                ['ID', 'IP', 'Expired At', 'CF Rule ID'],
                $expiredAttacks->map(fn ($attack) => [
                    $attack->id,
                    $attack->ip,
                    $attack->expiration_at->toDateTimeString(),
                    $attack->cf_rule_id ?? 'N/A',
                ]),
            );

            if (! $this->confirm('Proceed with cleanup?', true)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        foreach ($expiredAttacks as $attack) {
            if ($this->option('dry-run')) {
                $this->line("Would unban IP: {$attack->ip}");

                continue;
            }

            if ($attack->cf_rule_id) {
                UnbanFromCloudflare::dispatch($attack);
            }

            $attack->update([
                'is_blocked' => false,
                'cf_rule_id' => null,
            ]);

            $this->line("<fg=green>✓</> Unbanned IP: {$attack->ip}");
        }

        $this->newLine();
        $this->info('<fg=green>✓</> Cleanup complete!');

        return self::SUCCESS;
    }
}
