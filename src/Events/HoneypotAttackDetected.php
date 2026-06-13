<?php

declare(strict_types=1);

namespace HoneypotPlus\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class HoneypotAttackDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $ip,
        public string $honeypotRule,
        public ?string $userAgent,
        public string $httpMethod,
        public string $pathRequested,
    ) {}
}
