<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Facades;

use HoneypotPlus\Facades\HoneypotPlus as HoneypotPlusFacade;
use HoneypotPlus\HoneypotPlus as HoneypotPlusService;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('facade provides access to isBanned method', function () {
    HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
    ]);

    expect(HoneypotPlusFacade::isBanned('192.168.1.100'))->toBeTrue();
    expect(HoneypotPlusFacade::isBanned('192.168.1.999'))->toBeFalse();
});

test('facade provides access to getBannedRecord method', function () {
    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
    ]);

    $record = HoneypotPlusFacade::getBannedRecord('192.168.1.100');

    expect($record)->not->toBeNull();
    expect($record->id)->toBe($attack->id);
});

test('facade provides access to ban method', function () {
    Http::fake();

    $attack = HoneypotPlusFacade::ban('192.168.1.100', 24);

    expect($attack)->not->toBeNull();
    expect($attack->ip)->toBe('192.168.1.100');
});

test('facade provides access to unban method', function () {
    HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '192.168.1.100',
    ]);

    $result = HoneypotPlusFacade::unban('192.168.1.100');

    expect($result)->toBeTrue();
});

test('facade provides access to getStats method', function () {
    HoneypotPlusAttack::factory()->count(5)->create();

    $stats = HoneypotPlusFacade::getStats();

    expect($stats['total'])->toBe(5);
});

test('facade resolves to service from container', function () {
    $facadeInstance = HoneypotPlusFacade::getFacadeRoot();
    expect($facadeInstance)->toBeInstanceOf(HoneypotPlusService::class);
});
