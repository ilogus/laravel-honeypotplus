<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Commands;

use HoneypotPlus\Commands\ManageCommand;
use HoneypotPlus\Jobs\BanViaCloudflare;
use HoneypotPlus\Jobs\UnbanFromCloudflare;
use HoneypotPlus\Models\HoneypotPlusAttack;
use Illuminate\Support\Facades\Bus;

test('manage command has correct signature', function () {
    $command = app(ManageCommand::class);

    expect($command->getName())->toBe('honeypot-plus:manage');
    expect($command->getDescription())->toBe('Manage honeypot blocked IPs interactively');
});

test('manage command exists in kernel', function () {
    $kernel = app()->make('Illuminate\Contracts\Console\Kernel');
    $commands = $kernel->all();

    expect($commands)->toHaveKey('honeypot-plus:manage');
});

test('manage command exits gracefully', function () {
    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsOutput('Goodbye!')
        ->assertExitCode(0);
});

test('manage command lists blocked ips', function () {
    HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '10.20.30.40',
        'honeypot_rule' => '/.env',
        'user_agent' => 'MaliciousBot/1.0',
    ]);

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'list', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);
});

test('manage command shows empty message when no blocked ips', function () {
    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'list', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsOutput('No blocked IPs found.')
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);
});

test('manage command bans an ip manually', function () {
    Bus::fake();

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'ban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to ban', '1.2.3.4')
        ->expectsConfirmation('Ban this IP?', 'yes')
        ->expectsChoice('Ban duration:', '24', [
            '1' => '1 hour',
            '24' => '24 hours',
            '168' => '7 days',
            '720' => '30 days',
        ])
        ->expectsOutput('✓ IP 1.2.3.4 banned for 24 hours.')
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);

    $attack = HoneypotPlusAttack::byIp('1.2.3.4')->first();
    expect($attack)->not->toBeNull();
    expect($attack->is_blocked)->toBeFalse();
    expect($attack->honeypot_rule)->toBe('manual-ban');
    expect($attack->http_method)->toBe('MANUAL');
    expect($attack->expiration_at)->not->toBeNull();
});

test('manage command prevents banning already banned ip', function () {
    Bus::fake();

    HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '5.6.7.8',
    ]);

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'ban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to ban', '5.6.7.8')
        ->expectsOutput('✗ IP 5.6.7.8 is already banned!')
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);
});

test('manage command cancels ban when not confirmed', function () {
    Bus::fake();

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'ban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to ban', '9.9.9.9')
        ->expectsConfirmation('Ban this IP?', 'no')
        ->expectsOutput('Cancelled.')
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);

    expect(HoneypotPlusAttack::byIp('9.9.9.9')->exists())->toBeFalse();
});

test('manage command bans with cloudflare when configured', function () {
    Bus::fake();

    config([
        'honeypot-plus.cloudflare_api_token' => 'test-token',
        'honeypot-plus.cloudflare_zone_id' => 'test-zone',
    ]);

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'ban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to ban', '3.3.3.3')
        ->expectsConfirmation('Ban this IP?', 'yes')
        ->expectsChoice('Ban duration:', '24', [
            '1' => '1 hour',
            '24' => '24 hours',
            '168' => '7 days',
            '720' => '30 days',
        ])
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);

    Bus::assertDispatched(BanViaCloudflare::class);
});

test('manage command does not dispatch cloudflare ban when not configured', function () {
    Bus::fake();

    config([
        'honeypot-plus.cloudflare_api_token' => null,
        'honeypot-plus.cloudflare_zone_id' => null,
    ]);

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'ban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to ban', '4.4.4.4')
        ->expectsConfirmation('Ban this IP?', 'yes')
        ->expectsChoice('Ban duration:', '1', [
            '1' => '1 hour',
            '24' => '24 hours',
            '168' => '7 days',
            '720' => '30 days',
        ])
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);

    Bus::assertNotDispatched(BanViaCloudflare::class);
});

test('manage command unbans an ip', function () {
    Bus::fake();

    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '6.6.6.6',
        'cf_rule_id' => null,
    ]);

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'unban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to unban', '6.6.6.6')
        ->expectsConfirmation('Unban IP 6.6.6.6?', 'yes')
        ->expectsOutput('✓ IP 6.6.6.6 unbanned.')
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);

    expect($attack->fresh()->is_blocked)->toBeFalse();
});

test('manage command shows error when unbanning non-banned ip', function () {
    Bus::fake();

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'unban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to unban', '9.9.9.9')
        ->expectsOutput('✗ IP 9.9.9.9 is not banned!')
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);
});

test('manage command cancels unban when not confirmed', function () {
    Bus::fake();

    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '7.7.7.7',
    ]);

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'unban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to unban', '7.7.7.7')
        ->expectsConfirmation('Unban IP 7.7.7.7?', 'no')
        ->expectsOutput('Cancelled.')
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);

    expect($attack->fresh()->is_blocked)->toBeTrue();
});

test('manage command dispatches unban from cloudflare for ips with cf rule id', function () {
    Bus::fake();

    $attack = HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '8.8.8.8',
        'cf_rule_id' => 'cf-rule-xyz',
    ]);

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'unban', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsQuestion('Enter the IP address to unban', '8.8.8.8')
        ->expectsConfirmation('Unban IP 8.8.8.8?', 'yes')
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);

    Bus::assertDispatched(UnbanFromCloudflare::class);
    expect($attack->fresh()->is_blocked)->toBeFalse();
});

test('manage command shows statistics', function () {
    HoneypotPlusAttack::factory()->count(3)->create([
        'is_blocked' => false,
    ]);

    HoneypotPlusAttack::factory()->blocked()->create([
        'ip' => '1.1.1.1',
    ]);

    HoneypotPlusAttack::factory()->expired()->blocked()->create([
        'ip' => '2.2.2.2',
    ]);

    HoneypotPlusAttack::factory()->reported()->create([
        'ip' => '3.3.3.3',
    ]);

    $this->artisan('honeypot-plus:manage')
        ->expectsChoice('What would you like to do?', 'stats', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->expectsChoice('Press Enter to continue...', 'continue', ['continue' => 'Continue'])
        ->expectsChoice('What would you like to do?', 'exit', [
            'list' => 'List blocked IPs',
            'ban' => 'Ban an IP manually',
            'unban' => 'Unban an IP',
            'stats' => 'Show statistics',
            'exit' => 'Exit',
        ])
        ->assertExitCode(0);
});
