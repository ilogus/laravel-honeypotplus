# Contributing to Laravel HoneypotPlus

Thank you for considering contributing to Laravel HoneypotPlus! This document provides guidelines and instructions for contributing.

## Code Standards

Laravel HoneypotPlus follows the coding standards of the Laravel framework:

- **PSR-12**: We follow PSR-12 coding standards.
- **Laravel Pint**: Use `vendor/bin/pint` to automatically fix code style issues.
- **Type Declarations**: All methods must have explicit return type declarations and parameter type hints.
- **Strict Types**: All files must declare `strict_types=1`.

## Running Tests

Run the test suite before submitting a pull request:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## Development Workflow

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/your-feature-name`
3. **Make your changes**:
   - Write clean, readable code following PSR-12 standards
   - Add tests for new functionality
   - Update documentation if needed
4. **Run tests**: `composer test`
5. **Run code style check**: `composer pint-test`
6. **Commit your changes** using [Conventional Commits](https://www.conventionalcommits.org/):
   - `feat: add support for custom ban durations`
   - `fix: resolve Cloudflare API timeout issue`
   - `docs: update installation instructions`
7. **Push to your fork**: `git push origin feature/your-feature-name`
8. **Create a Pull Request** on GitHub

> **💡 Tip:** We recommend signing your commits with GPG. See [GitHub's documentation](https://docs.github.com/en/authentication/managing-commit-signature-verification/signing-commits) for setup instructions.

## Pull Request Guidelines

- **Describe your changes**: Provide a clear description of what you've changed and why
- **Link issues**: Reference related issues using `#issue-number`
- **Test coverage**: Ensure new features include tests
- **Documentation**: Update README.md if you've added user-facing features
- **One feature per PR**: Keep pull requests focused on a single feature or bug fix
- **Clean history**: Squash commits if necessary to keep the history clean

## Project Structure

```
.
├── src/
│   ├── Commands/        # Artisan commands
│   ├── Events/          # Event classes
│   ├── Facades/         # Facade classes
│   ├── Jobs/            # Job classes
│   ├── Listeners/       # Event listeners
│   ├── Middleware/      # Middleware classes
│   ├── Models/          # Eloquent models
│   ├── HoneypotPlus.php # Facade
│   └── HoneypotPlusServiceProvider.php
├── tests/
│   ├── Feature/        # Feature tests
│   └── Unit/           # Unit tests
├── config/             # Configuration files
├── database/           # Database migrations and factories
└── README.md
```

## Adding New Features

When adding new features:

1. **Write tests first**: Follow Test-Driven Development (TDD) when possible
2. **Keep it simple**: Write clean, maintainable code
3. **Document**: Add PHPDoc blocks for classes and methods
4. **Type hint**: Use strict types and explicit type declarations
5. **Test**: Ensure all tests pass including edge cases

## Reporting Bugs

When reporting bugs, please include:

- Laravel version
- PHP version
- Package version
- Steps to reproduce the issue
- Expected behavior
- Actual behavior
- Error messages or stack traces

## Security Vulnerabilities

If you discover a security vulnerability, please email **contact@ilogus.dev** instead of using the issue tracker.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
