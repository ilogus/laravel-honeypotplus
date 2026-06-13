<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Jobs;

use HoneypotPlus\Jobs\UnbanFromCloudflare;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('it unbans ip via cloudflare when rule exists', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $job = new UnbanFromCloudflare($attack);
    $job->handle();

    expect($attack->fresh()->cf_rule_id)->toBeNull();
    expect($attack->fresh()->is_blocked)->toBeFalse();
});

test('it skips unban when no cloudflare rule exists', function () {
    Http::fake();

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'cf_rule_id' => null,
    ]);

    $job = new UnbanFromCloudflare($attack);
    $job->handle();

    Http::assertNothingSent();
});

test('it skips unban when cloudflare credentials are not configured', function () {
    Http::fake();

    Config::set('honeypot-plus.cloudflare_api_token', null);
    Config::set('honeypot-plus.cloudflare_zone_id', null);

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $job = new UnbanFromCloudflare($attack);
    $job->handle();

    Http::assertNothingSent();
    expect($attack->fresh()->cf_rule_id)->toBe('cf-rule-123');
});

test('it retries on failed http response', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['error' => 'not found'], 404),
    ]);

    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $job = new UnbanFromCloudflare($attack);
    $job->handle();

    expect($attack->fresh()->cf_rule_id)->toBe('cf-rule-123');
    expect($attack->fresh()->is_blocked)->toBeTrue();
});

test('it handles http exception', function () {
    Http::fake(function () {
        throw new \Exception('Connection timeout');
    });

    Config::set('honeypot-plus.cloudflare_api_token', 'test-cf-token');
    Config::set('honeypot-plus.cloudflare_zone_id', 'test-zone-id');

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $job = new UnbanFromCloudflare($attack);

    expect(fn () => $job->handle())->not->toThrow(\Exception::class);
});
