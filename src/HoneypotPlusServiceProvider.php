<?php

declare(strict_types=1);

namespace HoneypotPlus;

use HoneypotPlus\Commands\CleanupCommand;
use HoneypotPlus\Commands\InstallCommand;
use HoneypotPlus\Commands\ManageCommand;
use HoneypotPlus\Events\HoneypotAttackDetected;
use HoneypotPlus\Listeners\HandleHoneypotAttack;
use HoneypotPlus\Middleware\HoneypotPlusMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class HoneypotPlusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/honeypot-plus.php', 'honeypot-plus');

        $this->app->singleton(HoneypotPlus::class, fn ($app) => new HoneypotPlus($app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/honeypot-plus.php' => config_path('honeypot-plus.php'),
            ], 'honeypot-plus:config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'honeypot-plus:migrations');

            $this->commands([
                InstallCommand::class,
                ManageCommand::class,
                CleanupCommand::class,
            ]);
        }

        $this->app['router']->aliasMiddleware('honeypot', HoneypotPlusMiddleware::class);

        Event::listen(
            HoneypotAttackDetected::class,
            HandleHoneypotAttack::class
        );

        $this->app->booted(function () {
            if (! config('honeypot-plus.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $frequency = config('honeypot-plus.schedule_cleanup', 'daily');

            $validFrequencies = [
                'everyMinute', 'everyTwoMinutes', 'everyThreeMinutes', 'everyFourMinutes', 'everyFiveMinutes',
                'everyTenMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes', 'hourly', 'daily',
                'weekdays', 'weekends', 'sundays', 'mondays', 'tuesdays', 'wednesdays', 'thursdays', 'fridays', 'saturdays',
                'weekly', 'monthly', 'quarterly', 'yearly',
            ];

            if (! in_array($frequency, $validFrequencies)) {
                $frequency = 'daily';
            }

            $schedule->command('honeypot-plus:cleanup')
                ->$frequency()
                ->description('Clean up expired honeypot bans');
        });

        AboutCommand::add('HoneypotPlus', fn () => [
            'Status' => config('honeypot-plus.enabled', true) ? '<fg=green;options=bold>Enabled</>' : '<fg=gray;options=bold>Disabled</>',
            'Logging' => config('honeypot-plus.logging', true) ? 'Enabled' : 'Disabled',
            'AbuseIPDB' => ! empty(env('HONEYPOT_PLUS_ABUSEIPDB_KEY')) ? 'Configured' : 'Not configured',
            'Cloudflare' => ! empty(env('HONEYPOT_PLUS_CLOUDFLARE_API_TOKEN')) && ! empty(env('HONEYPOT_PLUS_CLOUDFLARE_ZONE_ID')) ? 'Configured' : 'Not configured',
        ]);
    }
}
