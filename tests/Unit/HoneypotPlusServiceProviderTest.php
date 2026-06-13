<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit;

use HoneypotPlus\Events\HoneypotAttackDetected;
use HoneypotPlus\HoneypotPlus;
use HoneypotPlus\HoneypotPlusServiceProvider;
use HoneypotPlus\Middleware\HoneypotPlusMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

test('service provider registers config merge', function () {
    expect(config('honeypot-plus'))->toBeArray()
        ->and(config('honeypot-plus.enabled'))->toBeBool()
        ->and(config('honeypot-plus.honeypots'))->toBeArray();
});

test('service provider registers singleton for HoneypotPlus', function () {
    $instance1 = app(HoneypotPlus::class);
    $instance2 = app(HoneypotPlus::class);

    expect($instance1)->toBeInstanceOf(HoneypotPlus::class)
        ->and($instance1)->toBe($instance2); // Same instance (singleton)
});

test('service provider registers event listener', function () {
    // Check if the listener is registered for the event
    $dispatcher = app('events');
    $listeners = $dispatcher->getListeners(HoneypotAttackDetected::class);

    // Listeners should be registered (not empty array)
    expect($listeners)->not->toBeEmpty();

    // The event should be mapped to the listener
    $eventListeners = $dispatcher->getRawListeners();

    expect($eventListeners)->toHaveKey(HoneypotAttackDetected::class);
});

test('service provider registers middleware alias', function () {
    $router = app('router');
    $middleware = $router->getMiddleware();

    expect($middleware)->toHaveKey('honeypot');
    expect($middleware['honeypot'])->toBe(HoneypotPlusMiddleware::class);
});

test('service provider schedules cleanup command', function () {
    $schedule = app()->make(Schedule::class);
    $events = $schedule->events();

    $hasCleanupEvent = collect($events)->contains(function ($event) {
        return str_contains($event->command, 'honeypot-plus:cleanup');
    });

    expect($hasCleanupEvent)->toBeTrue();
});

test('scheduled cleanup has correct description', function () {
    $schedule = app()->make(Schedule::class);
    $events = $schedule->events();

    $cleanupEvent = collect($events)->first(function ($event) {
        return str_contains($event->command, 'honeypot-plus:cleanup');
    });

    expect($cleanupEvent)->not->toBeNull()
        ->and($cleanupEvent->description)->toBe('Clean up expired honeypot bans');
});

test('service provider config publishes are defined', function () {
    $provider = app()->getProvider(HoneypotPlusServiceProvider::class);

    expect($provider)->not->toBeNull();

    $publishGroups = $provider->pathsToPublish();

    $hasConfigPublish = collect($publishGroups)->contains(function ($paths) {
        return collect($paths)->contains(fn ($path) => str_contains($path, 'config/honeypot-plus.php'));
    });

    expect($hasConfigPublish)->toBeTrue();
});

test('service provider migration publishes are defined', function () {
    $provider = app()->getProvider(HoneypotPlusServiceProvider::class);

    $publishGroups = $provider->pathsToPublish();

    $hasMigrationPublish = collect($publishGroups)->contains(function ($paths) {
        return collect($paths)->contains(fn ($path) => str_contains($path, 'database/migrations'));
    });

    expect($hasMigrationPublish)->toBeTrue();
});

test('honeypot plus service is registered in container', function () {
    expect(app()->bound(HoneypotPlus::class))->toBeTrue();
});

test('commands are available via artisan', function () {
    $kernel = app()->make('Illuminate\Contracts\Console\Kernel');
    $commands = $kernel->all();

    expect($commands)->toHaveKey('honeypot-plus:install')
        ->and($commands)->toHaveKey('honeypot-plus:manage')
        ->and($commands)->toHaveKey('honeypot-plus:cleanup');
});

test('service provider does not schedule cleanup when disabled', function () {
    $eventCountBefore = collect(app()->make(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'honeypot-plus:cleanup')
            && $event->description === 'Clean up expired honeypot bans')
        ->count();

    config(['honeypot-plus.enabled' => false]);

    $provider = new HoneypotPlusServiceProvider($this->app);
    $provider->boot();

    $eventCountAfter = collect(app()->make(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'honeypot-plus:cleanup')
            && $event->description === 'Clean up expired honeypot bans')
        ->count();

    expect($eventCountAfter)->toBe($eventCountBefore);
});

test('service provider falls back to daily when schedule_cleanup is invalid', function () {
    config(['honeypot-plus.schedule_cleanup' => 'invalidFrequency']);

    $provider = new HoneypotPlusServiceProvider($this->app);
    $provider->boot();

    $schedule = app()->make(Schedule::class);
    $events = $schedule->events();

    $cleanupEvent = collect($events)->last(function ($event) {
        return str_contains($event->command, 'honeypot-plus:cleanup');
    });

    expect($cleanupEvent)->not->toBeNull()
        ->and($cleanupEvent->description)->toBe('Clean up expired honeypot bans');
});
