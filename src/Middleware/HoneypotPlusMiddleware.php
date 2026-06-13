<?php

declare(strict_types=1);

namespace HoneypotPlus\Middleware;

use Closure;
use HoneypotPlus\Events\HoneypotAttackDetected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class HoneypotPlusMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('honeypot-plus.enabled', true)) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $honeypots = config('honeypot-plus.honeypots', []);

        $matchedRule = $this->matchHoneypot($path, $honeypots);

        if ($matchedRule) {
            event(new HoneypotAttackDetected(
                ip: $request->ip(),
                honeypotRule: $matchedRule,
                userAgent: $request->userAgent(),
                httpMethod: $request->method(),
                pathRequested: $path,
            ));

            if (config('honeypot-plus.logging', true)) {
                Log::warning('[HoneypotPlus] Honeypot triggered', [
                    'ip' => $request->ip(),
                    'path' => $path,
                    'rule' => $matchedRule,
                    'method' => $request->method(),
                ]);
            }

            abort(config('honeypot-plus.exception_status', 403));
        }

        return $next($request);
    }

    private function matchHoneypot(string $path, array $honeypots): ?string
    {
        foreach ($honeypots as $pattern) {
            if (str_starts_with($pattern, 'regex:')) {
                $regex = substr($pattern, 6);

                if (@preg_match($regex, $path)) {
                    return $pattern;
                }
            } else {
                if ($path === $pattern || str_starts_with($path, rtrim($pattern, '/').'/')) {
                    return $pattern;
                }
            }
        }

        return null;
    }
}
