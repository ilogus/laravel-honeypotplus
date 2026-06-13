<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Listeners;

use HoneypotPlus\Events\HoneypotAttackDetected;
use HoneypotPlus\Jobs\BanViaCloudflare;
use HoneypotPlus\Jobs\ReportToAbuseIPDB;
use HoneypotPlus\Listeners\HandleHoneypotAttack;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

test('it creates new attack record for first time offender', function () {
    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/.env',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/.env',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    expect(HoneypotPlusAttack::count())->toBe(1);

    $attack = HoneypotPlusAttack::first();
    expect($attack->ip)->toBe('192.168.1.100');
    expect($attack->honeypot_rule)->toBe('/.env');
    expect($attack->user_agent)->toBe('BadBot/1.0');
    expect($attack->http_method)->toBe('GET');
    expect($attack->path_requested)->toBe('/.env');
});

test('it does not create duplicate attack for already banned ip', function () {
    $existingAttack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->addHours(24),
        'last_seen_at' => now()->subHour(),
    ]);

    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/wp-config.php',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/wp-config.php',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    expect(HoneypotPlusAttack::count())->toBe(1);

    $existingAttack->refresh();
    expect($existingAttack->last_seen_at)->not->toBeNull();
});

test('it dispatches report job when abuseipdb key is configured', function () {
    Config::set('honeypot-plus.abuseipdb_key', 'test-abuse-key');

    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/.env',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/.env',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    Queue::assertPushed(ReportToAbuseIPDB::class);
});

test('it does not dispatch report job when abuseipdb key is missing', function () {
    Config::set('honeypot-plus.abuseipdb_key', null);

    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/.env',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/.env',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    Queue::assertNotPushed(ReportToAbuseIPDB::class);
});

test('it dispatches ban job when cloudflare credentials are configured', function () {
    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');

    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/.env',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/.env',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    Queue::assertPushed(BanViaCloudflare::class);
});

test('it does not dispatch ban job when cloudflare credentials are missing', function () {
    Config::set('honeypot-plus.cloudflare_api_token', null);
    Config::set('honeypot-plus.cloudflare_zone_id', null);

    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/.env',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/.env',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    Queue::assertNotPushed(BanViaCloudflare::class);
});

test('it updates last_seen for repeat offender without creating new record', function () {
    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->addHours(24),
        'last_seen_at' => now()->subHour(),
    ]);

    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/.env',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/.env',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    expect(HoneypotPlusAttack::count())->toBe(1);

    Queue::assertNotPushed(ReportToAbuseIPDB::class);
    Queue::assertNotPushed(BanViaCloudflare::class);
});

test('it allows new attack after previous ban expired', function () {
    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
    ]);

    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/wp-config.php',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/wp-config.php',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    expect(HoneypotPlusAttack::count())->toBe(2);
});
