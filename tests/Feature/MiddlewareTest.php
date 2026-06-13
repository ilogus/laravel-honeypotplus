<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Feature;

use HoneypotPlus\Events\HoneypotAttackDetected;
use HoneypotPlus\Middleware\HoneypotPlusMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('it detects honeypot access on static route', function () {
    Event::fake([HoneypotAttackDetected::class]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');
    $request->setLaravelSession(app('session')->driver());
    $request->server->set('REMOTE_ADDR', '192.168.1.1');

    $exception = null;
    try {
        $middleware->handle($request, fn () => response('OK'));
    } catch (HttpException $e) {
        $exception = $e;
    }

    Event::assertDispatched(HoneypotAttackDetected::class);
    expect($exception)->not->toBeNull()
        ->and($exception->getStatusCode())->toBe(403);
});

test('it detects honeypot access on regex pattern', function () {
    Event::fake([HoneypotAttackDetected::class]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env.backup', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');

    $exception = null;
    try {
        $middleware->handle($request, fn () => response('OK'));
    } catch (HttpException $e) {
        $exception = $e;
    }

    Event::assertDispatched(HoneypotAttackDetected::class);
    expect($exception)->not->toBeNull()
        ->and($exception->getStatusCode())->toBe(403);
});

test('it allows normal requests', function () {
    Event::fake([HoneypotAttackDetected::class]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/home', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');

    $response = $middleware->handle($request, fn () => response('OK'));

    Event::assertNotDispatched(HoneypotAttackDetected::class);
    expect($response->status())->toBe(200);
});

test('it does nothing when disabled', function () {
    config()->set('honeypot-plus.enabled', false);
    Event::fake([HoneypotAttackDetected::class]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');

    $response = $middleware->handle($request, fn () => response('OK'));

    Event::assertNotDispatched(HoneypotAttackDetected::class);
    expect($response->status())->toBe(200);
});
