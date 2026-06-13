<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Middleware;

use HoneypotPlus\Events\HoneypotAttackDetected;
use HoneypotPlus\Middleware\HoneypotPlusMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config([
        'honeypot-plus.enabled' => true,
        'honeypot-plus.logging' => true,
        'honeypot-plus.honeypots' => [
            '/.env',
            '/wp-content',
            '/wp-admin',
            '/config',
            'regex:/^.*\.env\..*$/i',
        ],
    ]);
});

test('middleware allows normal requests', function () {
    Event::fake();

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/normal-page', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('OK');

    Event::assertNotDispatched(HoneypotAttackDetected::class);
});

test('middleware blocks direct .env access', function () {
    Event::fake();

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');

    $exception = null;
    try {
        $middleware->handle($request, fn ($req) => response('OK'));
    } catch (HttpException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getStatusCode())->toBe(403);

    Event::assertDispatched(HoneypotAttackDetected::class, function ($event) {
        return $event->ip === '192.168.1.1'
            && $event->honeypotRule === '/.env'
            && $event->pathRequested === '/.env'
            && $event->httpMethod === 'GET';
    });
});

test('middleware blocks wp-content access', function () {
    Event::fake();

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/wp-content/plugins/evil.php', 'GET');
    $request->server->set('REMOTE_ADDR', '10.0.0.1');

    $exception = null;
    try {
        $middleware->handle($request, fn ($req) => response('OK'));
    } catch (HttpException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getStatusCode())->toBe(403);

    Event::assertDispatched(HoneypotAttackDetected::class, function ($event) {
        return $event->pathRequested === '/wp-content/plugins/evil.php'
            && $event->honeypotRule === '/wp-content';
    });
});

test('middleware blocks requests matching regex pattern', function () {
    Event::fake();

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env.backup', 'GET');
    $request->server->set('REMOTE_ADDR', '172.16.0.1');

    $exception = null;
    try {
        $middleware->handle($request, fn ($req) => response('OK'));
    } catch (HttpException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getStatusCode())->toBe(403);

    Event::assertDispatched(HoneypotAttackDetected::class, function ($event) {
        return $event->pathRequested === '/.env.backup'
            && $event->honeypotRule === 'regex:/^.*\.env\..*$/i';
    });
});

test('middleware logs when enabled', function () {
    Event::fake();
    Log::spy();

    config(['honeypot-plus.logging' => true]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'POST');
    $request->server->set('REMOTE_ADDR', '1.2.3.4');

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
    } catch (HttpException $e) {
        // Expected exception
    }

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('[HoneypotPlus] Honeypot triggered', \Mockery::on(function ($context) {
            return isset($context['ip'])
                && $context['ip'] === '1.2.3.4'
                && $context['path'] === '/.env'
                && $context['rule'] === '/.env'
                && $context['method'] === 'POST';
        }));
});

test('middleware does not log when disabled', function () {
    Event::fake();
    Log::spy();

    config(['honeypot-plus.logging' => false]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');
    $request->server->set('REMOTE_ADDR', '5.6.7.8');

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
    } catch (HttpException $e) {
        // Expected exception
    }

    Log::shouldNotHaveReceived('warning');
});

test('middleware bypasses when disabled', function () {
    Event::fake();

    config(['honeypot-plus.enabled' => false]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('OK');

    Event::assertNotDispatched(HoneypotAttackDetected::class);
});

test('middleware captures user agent', function () {
    Event::fake();

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');
    $request->server->set('REMOTE_ADDR', '9.9.9.9');
    $request->headers->set('User-Agent', 'BadScanner/1.0');

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
    } catch (HttpException $e) {
        // Expected exception
    }

    Event::assertDispatched(HoneypotAttackDetected::class, function ($event) {
        return $event->userAgent === 'BadScanner/1.0';
    });
});

test('middleware handles null user agent', function () {
    Event::fake();

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');
    $request->server->set('REMOTE_ADDR', '8.8.8.8');
    $request->headers->remove('User-Agent');

    try {
        $middleware->handle($request, fn ($req) => response('OK'));
    } catch (HttpException $e) {
        // Expected exception
    }

    Event::assertDispatched(HoneypotAttackDetected::class, function ($event) {
        return $event->userAgent === null;
    });
});

test('middleware handles different http methods', function () {
    Event::fake();

    $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    foreach ($methods as $method) {
        Event::fake();

        $middleware = new HoneypotPlusMiddleware;
        $request = Request::create('/.env', $method);
        $request->server->set('REMOTE_ADDR', '7.7.7.7');

        try {
            $middleware->handle($request, fn ($req) => response('OK'));
        } catch (HttpException $e) {
            // Expected exception
        }

        Event::assertDispatched(HoneypotAttackDetected::class, function ($event) use ($method) {
            return $event->httpMethod === $method;
        });
    }
});

test('middleware matches trailing slash variants', function () {
    Event::fake();

    $middleware = new HoneypotPlusMiddleware;

    $paths = ['/wp-content', '/wp-content/', '/wp-content/uploads/file.jpg'];

    foreach ($paths as $path) {
        Event::fake();

        $request = Request::create($path, 'GET');
        $request->server->set('REMOTE_ADDR', '6.6.6.6');

        $exception = null;
        try {
            $middleware->handle($request, fn ($req) => response('OK'));
        } catch (HttpException $e) {
            $exception = $e;
        }

        expect($exception)->not->toBeNull()
            ->and($exception->getStatusCode())->toBe(403);
    }
});

test('middleware allows empty honeypot list', function () {
    Event::fake();

    config(['honeypot-plus.honeypots' => []]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getStatusCode())->toBe(200);

    Event::assertNotDispatched(HoneypotAttackDetected::class);
});

test('middleware handles invalid regex gracefully', function () {
    Event::fake();

    config([
        'honeypot-plus.honeypots' => [
            '/.env',
            'regex:/[invalid/', // Invalid regex
        ],
    ]);

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.env', 'GET');
    $request->server->set('REMOTE_ADDR', '3.3.3.3');

    $exception = null;
    try {
        $middleware->handle($request, fn ($req) => response('OK'));
    } catch (HttpException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getStatusCode())->toBe(403);

    Event::assertDispatched(HoneypotAttackDetected::class);
});

test('middleware is case sensitive by default for paths', function () {
    Event::fake();

    $middleware = new HoneypotPlusMiddleware;
    $request = Request::create('/.ENV', 'GET'); // Uppercase

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    // Default config has '/.env' not '/.ENV'
    // So uppercase should pass through
    expect($response->getStatusCode())->toBe(200);

    Event::assertNotDispatched(HoneypotAttackDetected::class);
});
