<?php

declare(strict_types=1);

namespace HoneypotPlus\Database\Factories;

use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Database\Eloquent\Factories\Factory;

final class HoneypotPlusAttackFactory extends Factory
{
    protected $model = HoneypotPlusAttack::class;

    public function definition(): array
    {
        return [
            'ip' => $this->faker->ipv4(),
            'honeypot_rule' => '/.env',
            'user_agent' => $this->faker->userAgent(),
            'http_method' => 'GET',
            'path_requested' => '/.env',
            'reported_at' => null,
            'cf_rule_id' => null,
            'expiration_at' => now()->addHours(24),
            'is_blocked' => false,
            'already_reported' => false,
            'last_seen_at' => now(),
        ];
    }

    public function blocked(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_blocked' => true,
            'expiration_at' => now()->addHours(24),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'expiration_at' => now()->subHours(1),
        ]);
    }

    public function reported(): self
    {
        return $this->state(fn (array $attributes) => [
            'reported_at' => now(),
            'already_reported' => true,
        ]);
    }
}
