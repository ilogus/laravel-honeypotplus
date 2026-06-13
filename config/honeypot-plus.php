<?php

declare(strict_types=1);

return [
    'enabled' => env('HONEYPOT_PLUS_ENABLE', true),
    'logging' => env('HONEYPOT_PLUS_LOGGING', true),

    'honeypots' => [
        // Static routes
        '/.env',
        '/.env.local',
        '/.env.production',
        '/wp-content',
        '/wp-admin',
        '/wp-login.php',
        '/.git',
        '/.gitignore',
        '/config',
        '/storage',
        '/vendor',
        '/composer.json',
        '/composer.lock',
        '/package.json',
        '/package-lock.json',
        '/README.md',
        '/.DS_Store',

        // Regex patterns (prefix with 'regex:')
        'regex:/^\/\.env/i',
        'regex:/wp-config\.php$/i',
        'regex:/\.git[a-z]*$/i',
        'regex:/\.idea\//i',
        'regex:/\.vscode\//i',
    ],

    'ban_duration_hours' => env('HONEYPOT_PLUS_BAN_DURATION_HOURS', 24),

    // Cloudflare
    'cloudflare_api_token' => env('HONEYPOT_PLUS_CLOUDFLARE_API_TOKEN'),
    'cloudflare_zone_id' => env('HONEYPOT_PLUS_CLOUDFLARE_ZONE_ID'),
    'block_category' => env('HONEYPOT_PLUS_BLOCK_CATEGORY', 'honeypot-probe'),

    // AbuseIPDB
    'abuseipdb_key' => env('HONEYPOT_PLUS_ABUSEIPDB_KEY'),

    'schedule_cleanup' => env('HONEYPOT_PLUS_SCHEDULE_CLEANUP', 'daily'),

    // HTTP exception status code when honeypot is triggered
    'exception_status' => env('HONEYPOT_PLUS_EXCEPTION_STATUS', 403),

    // AbuseIPDB categories: https://www.abuseipdb.com/categories
    // NOTE: Web App Attack (21) and Bad Web Bot (19) are the recommended defaults for honeypot reporting.
    // Modifying these categories is not advised unless you have extensive experience with AbuseIPDB,
    // as changes may negatively impact report quality and accuracy.
    'abuseipdb_categories' => [
        21, // Web App Attack
        19, // Bad Web Bot
    ],

    'abuseipdb_max_age_days' => env('HONEYPOT_PLUS_ABUSEIPDB_MAX_AGE_DAYS', 30),
];
