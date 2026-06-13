<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Commands;

use HoneypotPlus\Commands\CleanupCommand;
use HoneypotPlus\Jobs\UnbanFromCloudflare;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

test('cleanup command returns success when no expired bans exist', function () {
    Bus::fake();

    HoneypotPlusAttack::factory()->blocked()->create([
        'expiration_at' => now()->addDay(),
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true])
        ->expectsOutput('No expired bans to clean up.')
        ->assertExitCode(0);

    Bus::assertNotDispatched(UnbanFromCloudflare::class);
});

test('cleanup command finds expired bans and displays count', function () {
    Bus::fake();

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true])
        ->expectsOutput('Found 1 expired ban(s).')
        ->assertExitCode(0);
});

test('cleanup command proceeds with cleanup when force option is used', function () {
    Bus::fake();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true])
        ->expectsOutput('Found 1 expired ban(s).')
        ->assertExitCode(0);

    Bus::assertDispatched(UnbanFromCloudflare::class);
    expect($attack->fresh()->is_blocked)->toBeFalse();
});

test('cleanup command dry run option exists', function () {
    $command = $this->app->make(CleanupCommand::class);

    expect($command->getDefinition()->hasOption('dry-run'))->toBeTrue();
});

test('cleanup command dispatches unban job only for attacks with cf_rule_id', function () {
    Bus::fake();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.101',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => null,
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true])
        ->assertExitCode(0);

    Bus::assertDispatchedTimes(UnbanFromCloudflare::class, 1);
});

test('cleanup command marks attacks as unblocked', function () {
    Bus::fake();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true]);

    expect($attack->fresh()->is_blocked)->toBeFalse();
});

test('cleanup command handles multiple expired bans', function () {
    Bus::fake();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    HoneypotPlusAttack::factory()->count(3)->create([
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true])
        ->expectsOutput('Found 3 expired ban(s).')
        ->assertExitCode(0);

    Bus::assertDispatchedTimes(UnbanFromCloudflare::class, 3);
});

test('cleanup command displays completion message', function () {
    Bus::fake();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true])
        ->expectsOutput('✓ Cleanup complete!')
        ->assertExitCode(0);
});

test('cleanup command signature includes force and dry run options', function () {
    $command = $this->app->make(CleanupCommand::class);

    expect($command->getDefinition()->hasOption('force'))->toBeTrue();
    expect($command->getDefinition()->hasOption('dry-run'))->toBeTrue();
});

test('cleanup command unblocks attacks without cloudflare rule id', function () {
    Bus::fake();

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => null,
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true]);

    expect($attack->fresh()->is_blocked)->toBeFalse();
    Bus::assertNotDispatched(UnbanFromCloudflare::class);
});

test('cleanup command displays ip address when unblocking', function () {
    Bus::fake();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    HoneypotPlusAttack::factory()->create([
        'ip' => '10.20.30.40',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--force' => true])
        ->expectsOutput('✓ Unbanned IP: 10.20.30.40')
        ->assertExitCode(0);
});

test('cleanup command has correct signature', function () {
    $command = $this->app->make(CleanupCommand::class);

    expect($command->getName())->toBe('honeypot-plus:cleanup');
    expect($command->getDescription())->toBe('Clean up expired honeypot bans');
});

test('cleanup command dry run outputs would unban message', function () {
    Bus::fake();

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--dry-run' => true, '--force' => true])
        ->expectsOutput('Would unban IP: 192.168.1.100')
        ->assertExitCode(0);
});

test('cleanup command dry run does not update database', function () {
    Bus::fake();

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--dry-run' => true, '--force' => true])
        ->assertExitCode(0);

    expect($attack->fresh()->is_blocked)->toBeTrue();
    expect($attack->fresh()->cf_rule_id)->toBe('cf-rule-123');
});

test('cleanup command dry run does not dispatch unban jobs', function () {
    Bus::fake();

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup', ['--dry-run' => true, '--force' => true])
        ->assertExitCode(0);

    Bus::assertNotDispatched(UnbanFromCloudflare::class);
});

test('cleanup command asks for confirmation when not forced', function () {
    Bus::fake();
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup')
        ->expectsConfirmation('Proceed with cleanup?', 'yes')
        ->assertExitCode(0);
});

test('cleanup command can be cancelled via confirmation', function () {
    Bus::fake();

    $attack = HoneypotPlusAttack::factory()->create([
        'ip' => '192.168.1.100',
        'is_blocked' => true,
        'expiration_at' => now()->subHour(),
        'cf_rule_id' => 'cf-rule-123',
    ]);

    $this->artisan('honeypot-plus:cleanup')
        ->expectsConfirmation('Proceed with cleanup?', 'no')
        ->expectsOutput('Cancelled.')
        ->assertExitCode(0);

    expect($attack->fresh()->is_blocked)->toBeTrue();
    Bus::assertNotDispatched(UnbanFromCloudflare::class);
});
