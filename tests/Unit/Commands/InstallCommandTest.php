<?php

declare(strict_types=1);

namespace HoneypotPlus\Tests\Unit\Commands;

use HoneypotPlus\Commands\InstallCommand;

test('install command runs successfully with migrations confirmed', function () {
    $this->artisan('honeypot-plus:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'yes')
        ->expectsOutput('Installing HoneypotPlus...')
        ->expectsOutput('Configuration published!')
        ->expectsOutput('Migration published!')
        ->assertExitCode(0);
});

test('install command skips migrations when declined', function () {
    $this->artisan('honeypot-plus:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsOutput('Installing HoneypotPlus...')
        ->expectsOutput('Configuration published!')
        ->expectsOutput('Migration published!')
        ->assertExitCode(0);
});

test('install command displays success message', function () {
    $this->artisan('honeypot-plus:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsOutput('✓ HoneypotPlus installed successfully!')
        ->assertExitCode(0);
});

test('install command displays next steps', function () {
    $this->artisan('honeypot-plus:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsOutput('Next steps:')
        ->assertExitCode(0);
});

test('install command has correct signature', function () {
    $command = $this->app->make(InstallCommand::class);

    expect($command->getName())->toBe('honeypot-plus:install');
    expect($command->getDescription())->toBe('Install the HoneypotPlus package (publish config and migrations)');
});

test('install command exists in kernel', function () {
    $kernel = $this->app->make('Illuminate\Contracts\Console\Kernel');
    $commands = $kernel->all();

    expect($commands)->toHaveKey('honeypot-plus:install');
});
