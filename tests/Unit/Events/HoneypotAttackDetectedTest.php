<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Events;

use HoneypotPlus\Events\HoneypotAttackDetected;

test('event can be instantiated with all parameters', function () {
    $event = new HoneypotAttackDetected(
        ip: '192.168.1.1',
        honeypotRule: '/.env',
        userAgent: 'BadBot/1.0',
        httpMethod: 'GET',
        pathRequested: '/.env',
    );

    expect($event->ip)->toBe('192.168.1.1')
        ->and($event->honeypotRule)->toBe('/.env')
        ->and($event->userAgent)->toBe('BadBot/1.0')
        ->and($event->httpMethod)->toBe('GET')
        ->and($event->pathRequested)->toBe('/.env');
});

test('event can be instantiated with null user agent', function () {
    $event = new HoneypotAttackDetected(
        ip: '10.0.0.1',
        honeypotRule: 'regex:/^.*\.env$/i',
        userAgent: null,
        httpMethod: 'POST',
        pathRequested: '/.env.local',
    );

    expect($event->ip)->toBe('10.0.0.1')
        ->and($event->honeypotRule)->toBe('regex:/^.*\.env$/i')
        ->and($event->userAgent)->toBeNull()
        ->and($event->httpMethod)->toBe('POST')
        ->and($event->pathRequested)->toBe('/.env.local');
});

test('event is dispatchable', function () {
    $event = new HoneypotAttackDetected(
        ip: '1.2.3.4',
        honeypotRule: '/wp-content',
        userAgent: 'Scanner',
        httpMethod: 'GET',
        pathRequested: '/wp-content/',
    );

    expect($event)->toBeInstanceOf(HoneypotAttackDetected::class);
});

test('event stores ipv6 address', function () {
    $event = new HoneypotAttackDetected(
        ip: '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        honeypotRule: '/config',
        userAgent: null,
        httpMethod: 'GET',
        pathRequested: '/config/database.php',
    );

    expect($event->ip)->toBe('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
});

test('event stores different http methods', function () {
    $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    foreach ($methods as $method) {
        $event = new HoneypotAttackDetected(
            ip: '127.0.0.1',
            honeypotRule: '/.env',
            userAgent: 'Test',
            httpMethod: $method,
            pathRequested: '/.env',
        );

        expect($event->httpMethod)->toBe($method);
    }
});
