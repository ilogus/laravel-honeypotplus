<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Jobs;

use HoneypotPlus\Jobs\BanViaCloudflare;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('it bans ip via cloudflare when credentials are configured', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => ['id' => 'cf-rule-123'],
        ], 200),
    ]);

    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');
    Config::set('honeypot-plus.block_category', 'honeypot-probe');

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => false,
        'cf_rule_id' => null,
    ]);

    $job = new BanViaCloudflare($attack);
    $job->handle();

    expect($attack->fresh()->cf_rule_id)->toBe('cf-rule-123');
    expect($attack->fresh()->is_blocked)->toBeTrue();
});

test('it does not ban already blocked ip', function () {
    Http::fake();

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'cf_rule_id' => 'existing-rule',
    ]);

    $job = new BanViaCloudflare($attack);
    $job->handle();

    Http::assertNothingSent();
    expect($attack->fresh()->cf_rule_id)->toBe('existing-rule');
});

test('it skips ban when cloudflare credentials are not configured', function () {
    Http::fake();
    Config::set('honeypot-plus.cloudflare_api_token', null);
    Config::set('honeypot-plus.cloudflare_zone_id', null);

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => false,
    ]);

    $job = new BanViaCloudflare($attack);
    $job->handle();

    Http::assertNothingSent();
});

test('it retries on failed http response', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => false,
    ]);

    $job = new BanViaCloudflare($attack);
    $job->handle();

    expect($attack->fresh()->cf_rule_id)->toBeNull();
    expect($attack->fresh()->is_blocked)->toBeFalse();
});

test('it handles http exception', function () {
    Http::fake(function () {
        throw new \Exception('Connection timeout');
    });

    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => false,
    ]);

    $job = new BanViaCloudflare($attack);

    expect(fn () => $job->handle())->not->toThrow(\Exception::class);
});
