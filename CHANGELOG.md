# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-13

### Added
- Initial release of Laravel HoneypotPlus package
- Honeypot detection middleware for sensitive paths
- Cloudflare integration for automatic IP banning
- AbuseIPDB reporting for malicious IPs
- Interactive CLI management (`php artisan honeypot-plus:manage`)
- Automatic cleanup of expired bans via Laravel scheduler
- Event-driven architecture for extensibility
- Database migration for attack tracking
- Comprehensive test coverage
- Support for Laravel 12.x and 13.x
- Support for PHP 8.3, 8.4 and 8.5

[Unreleased]: https://github.com/ilogus/laravel-honeypotplus/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ilogus/laravel-honeypotplus/releases/tag/v1.0.0
