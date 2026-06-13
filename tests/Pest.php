<?php

declare(strict_types=1);

use HoneypotPlus\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

expect()->extend('toBeOne', fn () => expect($this->value)->toBe(1));
