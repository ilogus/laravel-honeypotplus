<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Feature;

use HoneypotPlus\Events\HoneypotAttackDetected;
use HoneypotPlus\Jobs\BanViaCloudflare;
use HoneypotPlus\Jobs\ReportToAbuseIPDB;
use HoneypotPlus\Listeners\HandleHoneypotAttack;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('complete flow creates attack record and dispatches jobs', function () {
    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');
    Config::set('honeypot-plus.abuseipdb_key', 'test-abuse-key');

    Queue::fake();

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
    expect($attack->path_requested)->toBe('/.env');

    Queue::assertPushed(BanViaCloudflare::class);
    Queue::assertPushed(ReportToAbuseIPDB::class);
});

test('repeat attack does not create new record or dispatch jobs', function () {
    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');
    Config::set('honeypot-plus.abuseipdb_key', 'test-abuse-key');

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->addHours(24),
    ]);

    Queue::fake();

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

    Queue::assertNotPushed(BanViaCloudflare::class);
    Queue::assertNotPushed(ReportToAbuseIPDB::class);
});

test('new attack after expiration creates new record', function () {
    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
    ]);

    Queue::fake();

    $event = new HoneypotAttackDetected(
        ip: '192.168.1.100',
        honeypotRule: '/.env',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/.env',
    );

    $listener = new HandleHoneypotAttack;
    $listener->handle($event);

    expect(HoneypotPlusAttack::count())->toBe(2);

    Queue::assertPushed(BanViaCloudflare::class);
});

test('multiple different attacks create separate records', function () {
    Queue::fake();

    $listener = new HandleHoneypotAttack;

    $ips = ['192.168.1.100', '192.168.1.101', '192.168.1.102'];

    foreach ($ips as $ip) {
        $event = new HoneypotAttackDetected(
            ip: $ip,
            honeypotRule: '/.env',
            userAgent: 'BadBot/1.0',
            httpMethod: 'GET',
            pathRequested: '/.env',
        );

        $listener->handle($event);
    }

    expect(HoneypotPlusAttack::count())->toBe(3);
});
