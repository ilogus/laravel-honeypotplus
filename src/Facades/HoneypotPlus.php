<?php

declare(strict_types=1);

namespace HoneypotPlus\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isBanned(string $ip)
 * @method static \HoneypotPlus\Models\HoneypotPlusAttack|null getBannedRecord(string $ip)
 * @method static \HoneypotPlus\Models\HoneypotPlusAttack ban(string $ip, int $hours = 24)
 * @method static bool unban(string $ip)
 * @method static array getStats()
 *
 * @see \HoneypotPlus\HoneypotPlus
 */
final class HoneypotPlus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HoneypotPlus\HoneypotPlus::class;
    }
}
