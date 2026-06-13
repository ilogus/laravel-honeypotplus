<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit;

use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('it creates a honeypot attack record', function () {
    $attack = HoneypotPlusAttack::create([
        'ip' => '192.168.1.1',
        'honeypot_rule' => '/.env',
        'user_agent' => 'Mozilla/5.0',
        'http_method' => 'GET',
        'path_requested' => '/.env',
        'expiration_at' => now()->addHours(24),
        'is_blocked' => true,
    ]);

    expect($attack->ip)->toBe('192.168.1.1');
    expect($attack->is_blocked)->toBeTrue();
    expect($attack->isBanned())->toBeTrue();
});

test('it scopes active attacks', function () {
    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.1',
        'expiration_at' => now()->addHours(24),
        'is_blocked' => true,
    ]);

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.2',
        'expiration_at' => now()->subHour(),
        'is_blocked' => true,
    ]);

    $activeAttacks = HoneypotPlusAttack::active()->get();

    expect($activeAttacks)->toHaveCount(1);
    expect($activeAttacks->first()->ip)->toBe('192.168.1.1');
});

test('it scopes attacks by ip', function () {
    HoneypotPlusAttack::factory()->create(['ip' => '192.168.1.1']);
    HoneypotPlusAttack::factory()->create(['ip' => '192.168.1.2']);

    $attacks = HoneypotPlusAttack::byIp('192.168.1.1')->get();

    expect($attacks)->toHaveCount(1);
    expect($attacks->first()->ip)->toBe('192.168.1.1');
});

test('it scopes expired attacks', function () {
    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.1',
        'expiration_at' => now()->addHours(24),
    ]);

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.2',
        'expiration_at' => now()->subHour(),
    ]);

    $expiredAttacks = HoneypotPlusAttack::expired()->get();

    expect($expiredAttacks)->toHaveCount(1);
    expect($expiredAttacks->first()->ip)->toBe('192.168.1.2');
});

test('it marks as reported', function () {
    $attack = HoneypotPlusAttack::factory()->create([
        'already_reported' => false,
        'reported_at' => null,
    ]);

    $attack->markAsReported();

    expect($attack->fresh()->already_reported)->toBeTrue();
    expect($attack->fresh()->reported_at)->not->toBeNull();
});

test('it updates last seen', function () {
    $attack = HoneypotPlusAttack::factory()->create([
        'last_seen_at' => now()->subHour(),
    ]);

    $oldLastSeen = $attack->last_seen_at;
    $attack->updateLastSeen();

    expect($attack->fresh()->last_seen_at)->not->toEqual($oldLastSeen);
});

test('it returns correct prunable query', function () {
    HoneypotPlusAttack::factory()->create([
        'expiration_at' => now()->subMonths(7),
    ]);

    HoneypotPlusAttack::factory()->create([
        'expiration_at' => now()->subMonths(3),
    ]);

    $model = new HoneypotPlusAttack;
    $prunable = $model->prunable();

    expect($prunable->count())->toBe(1);
});

test('isBanned returns false when not blocked', function () {
    $attack = HoneypotPlusAttack::factory()->create([
        'is_blocked' => false,
        'expiration_at' => now()->addHours(24),
    ]);

    expect($attack->isBanned())->toBeFalse();
});

test('isBanned returns false when expiration is past', function () {
    $attack = HoneypotPlusAttack::factory()->create([
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
    ]);

    expect($attack->isBanned())->toBeFalse();
});

test('it casts attributes correctly', function () {
    $attack = HoneypotPlusAttack::create([
        'ip' => '192.168.1.1',
        'honeypot_rule' => '/.env',
        'http_method' => 'GET',
        'path_requested' => '/.env',
        'is_blocked' => 1,
        'already_reported' => 0,
        'expiration_at' => '2025-12-31 23:59:59',
        'reported_at' => null,
    ]);

    expect($attack->is_blocked)->toBeTrue();
    expect($attack->already_reported)->toBeFalse();
    expect($attack->expiration_at)->toBeInstanceOf(Carbon::class);
});
