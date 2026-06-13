<?php

declare(strict_types=1);

namespace HoneypotPlus\Commands;

use HoneypotPlus\Jobs\BanViaCloudflare;
use HoneypotPlus\Jobs\UnbanFromCloudflare;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class ManageCommand extends Command
{
    protected $signature = 'honeypot-plus:manage';

    protected $description = 'Manage honeypot blocked IPs interactively';

    public function handle(): int
    {
        $this->displayLogo();

        while (true) {
            $choice = select(
                label: 'What would you like to do?',
                options: [
                    'list' => 'List blocked IPs',
                    'ban' => 'Ban an IP manually',
                    'unban' => 'Unban an IP',
                    'stats' => 'Show statistics',
                    'exit' => 'Exit',
                ],
                scroll: 5,
            );

            match ($choice) {
                'list' => $this->listBlockedIps(),
                'ban' => $this->banIp(),
                'unban' => $this->unbanIp(),
                'stats' => $this->showStats(),
                'exit' => null,
            };

            if ($choice === 'exit') {
                break;
            }

            $this->newLine();
            select(
                label: 'Press Enter to continue...',
                options: ['continue' => 'Continue'],
            );
        }

        $this->info('<fg=green>Goodbye!</>');

        return self::SUCCESS;
    }

    private function displayLogo(): void
    {
        $this->newLine();
        $this->line('<fg=green;options=bold>Laravel HoneypotPlus</>');
        $this->newLine();
        $this->line('  <fg=blue>https://github.com/ilogus/Laravel-HoneypotPlus</>');
        $this->line('  <fg=gray>Found a bug? Open an issue: https://github.com/ilogus/Laravel-HoneypotPlus/issues</>');
        $this->newLine();
    }

    private function listBlockedIps(): void
    {
        $this->info('<fg=blue;options=bold>Blocked IPs</>');
        $this->newLine();

        $attacks = HoneypotPlusAttack::active()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($attacks->isEmpty()) {
            $this->comment('No blocked IPs found.');

            return;
        }

        $this->table(
            ['ID', 'IP', 'Rule', 'Expires At', 'User Agent'],
            $attacks->map(fn ($attack) => [
                $attack->id,
                $attack->ip,
                str($attack->honeypot_rule)->limit(30),
                $attack->expiration_at?->diffForHumans() ?? 'Never',
                str($attack->user_agent ?? 'N/A')->limit(30),
            ]),
        );
    }

    private function banIp(): void
    {
        $ip = text(
            label: 'Enter the IP address to ban',
            placeholder: '192.168.1.1',
            required: true,
        );

        $existing = HoneypotPlusAttack::byIp($ip)->active()->first();

        if ($existing) {
            $this->error("<fg=red>✗</> IP {$ip} is already banned!");

            return;
        }

        $this->table(
            ['IP', 'Action'],
            [[$ip, '<fg=red>Will be banned</>']],
        );

        if (! confirm('Ban this IP?', true)) {
            $this->info('Cancelled.');

            return;
        }

        $duration = select(
            label: 'Ban duration:',
            options: [
                '1' => '1 hour',
                '24' => '24 hours',
                '168' => '7 days',
                '720' => '30 days',
            ],
        );

        $hours = (int) $duration;

        $attack = HoneypotPlusAttack::create([
            'ip' => $ip,
            'honeypot_rule' => 'manual-ban',
            'http_method' => 'MANUAL',
            'path_requested' => '/manual-ban',
            'expiration_at' => now()->addHours($hours),
        ]);

        if ($this->shouldDispatchCloudflareJob()) {
            BanViaCloudflare::dispatch($attack);
        }

        $this->newLine();
        $this->info("<fg=green>✓</> IP {$ip} banned for {$hours} hours.");
    }

    private function unbanIp(): void
    {
        $ip = text(
            label: 'Enter the IP address to unban',
            placeholder: '192.168.1.1',
            required: true,
        );

        $attack = HoneypotPlusAttack::byIp($ip)->active()->first();

        if (! $attack) {
            $this->error("<fg=red>✗</> IP {$ip} is not banned!");

            return;
        }

        if (! confirm("Unban IP {$ip}?", true)) {
            $this->info('Cancelled.');

            return;
        }

        if ($attack->cf_rule_id) {
            UnbanFromCloudflare::dispatch($attack);
        }

        $attack->update([
            'is_blocked' => false,
            'expiration_at' => now(),
        ]);

        $this->newLine();
        $this->info("<fg=green>✓</> IP {$ip} unbanned.");
    }

    private function showStats(): void
    {
        $this->info('<fg=blue;options=bold>Statistics</>');
        $this->newLine();

        $total = HoneypotPlusAttack::count();
        $active = HoneypotPlusAttack::active()->count();
        $expired = HoneypotPlusAttack::expired()->count();
        $reported = HoneypotPlusAttack::whereNotNull('reported_at')->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['<fg=green>Total Attacks</>', $total],
                ['<fg=red>Currently Blocked</>', $active],
                ['<fg=gray>Expired Bans</>', $expired],
                ['<fg=blue>Reported to AbuseIPDB</>', $reported],
            ],
        );
    }

    private function shouldDispatchCloudflareJob(): bool
    {
        return ! empty(config('honeypot-plus.cloudflare_api_token'))
            && ! empty(config('honeypot-plus.cloudflare_zone_id'));
    }
}
