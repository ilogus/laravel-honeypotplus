<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit;

use HoneypotPlus\HoneypotPlus;
use HoneypotPlus\Jobs\BanViaCloudflare;
use HoneypotPlus\Jobs\UnbanFromCloudflare;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('isBanned returns true for banned ip', function () {
    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
        'expiration_at' => now()->addDay(),
    ]);

    $service = app(HoneypotPlus::class);

    expect($service->isBanned('192.168.1.100'))->toBeTrue();
});

test('isBanned returns false for non banned ip', function () {
    HoneypotPlusAttack::factory()->expired()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => false,
    ]);

    $service = app(HoneypotPlus::class);

    expect($service->isBanned('192.168.1.100'))->toBeFalse();
});

test('isBanned returns false when ip not found', function () {
    $service = app(HoneypotPlus::class);

    expect($service->isBanned('192.168.1.999'))->toBeFalse();
});

test('getBannedRecord returns record for banned ip', function () {
    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
    ]);

    $service = app(HoneypotPlus::class);

    $record = $service->getBannedRecord('192.168.1.100');

    expect($record)->not->toBeNull();
    expect($record->id)->toBe($attack->id);
});

test('getBannedRecord returns null for non banned ip', function () {
    $service = app(HoneypotPlus::class);

    expect($service->getBannedRecord('192.168.1.999'))->toBeNull();
});

test('ban creates new ban record', function () {
    Http::fake();

    $service = app(HoneypotPlus::class);

    $attack = $service->ban('192.168.1.100', 24);

    expect($attack)->not->toBeNull();
    expect($attack->ip)->toBe('192.168.1.100');
    expect($attack->honeypot_rule)->toBe('manual-ban');
    expect($attack->expiration_at)->greaterThan(now());
});

test('ban uses default duration when not specified', function () {
    Http::fake();

    $service = app(HoneypotPlus::class);

    $attack = $service->ban('192.168.1.100');

    $expectedExpiration = now()->addHours(24);
    // Check expiration is approximately 24 hours from now (within 1 minute margin)
    expect($attack->expiration_at->diffInMinutes($expectedExpiration))->toBeLessThan(2);
});

test('ban returns existing record when ip already banned', function () {
    Http::fake();

    $existing = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
        'expiration_at' => now()->addDay(),
    ]);

    $service = app(HoneypotPlus::class);

    $attack = $service->ban('192.168.1.100');

    expect($attack->id)->toBe($existing->id);
});

test('ban dispatches cloudflare job when credentials configured', function () {
    Bus::fake();
    Http::fake();

    config([
        'honeypot-plus.cloudflare_api_token' => 'test-token',
        'honeypot-plus.cloudflare_zone_id' => 'test-zone',
    ]);

    $service = new HoneypotPlus(app());
    $service->ban('192.168.1.100');

    Bus::assertDispatched(BanViaCloudflare::class);
});

test('ban does not dispatch cloudflare job when credentials missing', function () {
    Bus::fake();
    Http::fake();

    config([
        'honeypot-plus.cloudflare_api_token' => null,
        'honeypot-plus.cloudflare_zone_id' => null,
    ]);

    $service = new HoneypotPlus(app());
    $service->ban('192.168.1.100');

    Bus::assertNotDispatched(BanViaCloudflare::class);
});

test('unban returns false when ip not banned', function () {
    $service = app(HoneypotPlus::class);

    expect($service->unban('192.168.1.999'))->toBeFalse();
});

test('unban marks ip as unbanned', function () {
    Http::fake();

    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
        'cf_rule_id' => null,
    ]);

    $service = app(HoneypotPlus::class);

    expect($service->unban('192.168.1.100'))->toBeTrue();
    expect($attack->fresh()->is_blocked)->toBeFalse();
    expect($attack->fresh()->expiration_at)->lessThanOrEqualTo(now());
});

test('unban dispatches cloudflare job when cf_rule_id exists', function () {
    Bus::fake();
    Http::fake();

    config([
        'honeypot-plus.cloudflare_api_token' => 'test-token',
        'honeypot-plus.cloudflare_zone_id' => 'test-zone',
    ]);

    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $service = new HoneypotPlus(app());
    $service->unban('192.168.1.100');

    Bus::assertDispatched(UnbanFromCloudflare::class);
});

test('unban does not dispatch cloudflare job when no cf_rule_id', function () {
    Bus::fake();

    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
        'cf_rule_id' => null,
    ]);

    $service = app(HoneypotPlus::class);
    $service->unban('192.168.1.100');

    Bus::assertNotDispatched(UnbanFromCloudflare::class);
});

test('getStats returns correct statistics', function () {
    HoneypotPlusAttack::factory()->count(10)->create();
    HoneypotPlusAttack::factory()->blocked()->count(5)->create();
    HoneypotPlusAttack::factory()->expired()->count(3)->create();
    HoneypotPlusAttack::factory()->reported()->count(2)->create();

    $service = app(HoneypotPlus::class);
    $stats = $service->getStats();

    expect($stats)->toBeArray();
    expect($stats['total'])->toBe(10 + 5 + 3 + 2);
    expect($stats['active'])->toBe(5);
    expect($stats['expired'])->toBe(3);
    expect($stats['reported'])->toBe(2);
});

test('getStats returns zero when no attacks', function () {
    $service = app(HoneypotPlus::class);
    $stats = $service->getStats();

    expect($stats)->toBe([
        'total' => 0,
        'active' => 0,
        'expired' => 0,
        'reported' => 0,
    ]);
});
