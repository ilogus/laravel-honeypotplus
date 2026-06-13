<?php

declare(strict_types=1);

namespace HoneypotPlus\Commands;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'honeypot-plus:install';

    protected $description = 'Install the HoneypotPlus package (publish config and migrations)';

    public function handle(): int
    {
        $this->info('Installing HoneypotPlus...');

        $this->call('vendor:publish', [
            '--provider' => 'HoneypotPlus\HoneypotPlusServiceProvider',
            '--tag' => 'honeypot-plus:config',
        ]);

        $this->call('vendor:publish', [
            '--provider' => 'HoneypotPlus\HoneypotPlusServiceProvider',
            '--tag' => 'honeypot-plus:migrations',
        ]);

        $this->newLine();
        $this->info('Configuration published!');
        $this->info('Migration published!');
        $this->newLine();

        if ($this->confirm('Would you like to run the migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('<fg=green>✓</> HoneypotPlus installed successfully!');
        $this->newLine();
        $this->comment('Next steps:');
        $this->comment('1. Configure your .env file with AbuseIPDB and/or Cloudflare credentials');
        $this->comment('2. Add the honeypot middleware to your routes or kernel');
        $this->comment('3. Run <fg=blue>php artisan honeypot-plus:manage</> to manage blocked IPs');

        return self::SUCCESS;
    }
}
