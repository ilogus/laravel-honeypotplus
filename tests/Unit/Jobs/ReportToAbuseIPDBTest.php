<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Jobs;

use HoneypotPlus\Jobs\ReportToAbuseIPDB;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('it reports ip to abuseipdb when key is configured', function () {
    Http::fake([
        'api.abuseipdb.com/*' => Http::response([
            'data' => ['ipAddress' => '192.168.1.100'],
        ], 200),
    ]);

    Config::set('honeypot-plus.abuseipdb_key', 'test-abuse-key');
    Config::set('honeypot-plus.abuseipdb_categories', [21, 18]);
    Config::set('honeypot-plus.abuseipdb_max_age_days', 30);

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'path_requested' => '/.env',
        'honeypot_rule' => '/.env',
        'already_reported' => false,
        'reported_at' => null,
    ]);

    $job = new ReportToAbuseIPDB($attack);
    $job->handle();

    expect($attack->fresh()->already_reported)->toBeTrue();
    expect($attack->fresh()->reported_at)->not->toBeNull();
});

test('it does not report already reported ip', function () {
    Http::fake();

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'already_reported' => true,
        'reported_at' => now(),
    ]);

    $job = new ReportToAbuseIPDB($attack);
    $job->handle();

    Http::assertNothingSent();
});

test('it skips report when api key is not configured', function () {
    Http::fake();

    Config::set('honeypot-plus.abuseipdb_key', null);

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'already_reported' => false,
    ]);

    $job = new ReportToAbuseIPDB($attack);
    $job->handle();

    Http::assertNothingSent();
});

test('it skips report for old attacks', function () {
    Http::fake();

    Config::set('honeypot-plus.abuseipdb_key', 'test-abuse-key');
    Config::set('honeypot-plus.abuseipdb_max_age_days', 30);

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'already_reported' => false,
        'created_at' => now()->subDays(35),
    ]);

    $job = new ReportToAbuseIPDB($attack);
    $job->handle();

    Http::assertNothingSent();
});

test('it retries on failed http response', function () {
    Http::fake([
        'api.abuseipdb.com/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    Config::set('honeypot-plus.abuseipdb_key', 'test-abuse-key');

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'already_reported' => false,
    ]);

    $job = new ReportToAbuseIPDB($attack);
    $job->handle();

    expect($attack->fresh()->already_reported)->toBeFalse();
});

test('it handles http exception', function () {
    Http::fake(function () {
        throw new \Exception('Connection timeout');
    });

    Config::set('honeypot-plus.abuseipdb_key', 'test-abuse-key');

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'already_reported' => false,
    ]);

    $job = new ReportToAbuseIPDB($attack);

    expect(fn () => $job->handle())->not->toThrow(\Exception::class);
});
