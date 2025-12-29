# Contributing to Laravel Prometheus Metrics

Thank you for considering contributing!

## Code of Conduct

This project is governed by our Code of Conduct. By participating, you agree to uphold it.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, check existing issues. When reporting, include:

* Clear and descriptive title
* Exact steps to reproduce
* Specific examples
* Expected vs actual behavior
* Screenshots or logs if possible

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. Include:

* Clear and descriptive title
* Step-by-step description
* Specific examples
* Explanation of usefulness

### Pull Requests

* Fill in the required template
* Follow PSR-12 code style
* Include test cases
* End all files with newline
* Avoid platform-dependent code

## Styleguides

### Git Commit Messages

* Use present tense ("Add feature" not "Added feature")
* Use imperative mood ("Move cursor to...")
* Limit first line to 72 characters
* Reference issues liberally after first line
* Consider emoji:
    * 🎨 `:art:` - Improve structure/format
    * ⚡  `:zap:` - Improve performance
    * 🐛 `:bug:` - Fix a bug
    * ✨ `:sparkles:` - Introduce new features
    * 📝 `:memo:` - Documentation
    * 🚀 `:rocket:` - Deploy stuff
    * 💚 `:green_heart:` - Fix CI build
    * ✅ `:white_check_mark:` - Add tests

### PHP Styleguide

PSR-12 standard:

```php
<?php

namespace Faktly\LaravelPrometheusMetrics;

class ExampleClass
{
    public function exampleMethod()
    {
        // Method body
    }
}
```

* Indentation: 4 spaces
* Line length: Keep under 120 characters
* Naming: camelCase for methods, UPPER_CASE for constants
* Type hints: Always use

```php
public function collect(): array
{
    return [];
}

private function getCount(string $name): int
{
    return 0;
}
```

## Testing

```bash
composer test
composer test tests/Feature/MetricsEndpointTest.php
composer test:coverage
```

Write tests for:
* New features
* Bug fixes
* Edge cases

```php
<?php

namespace Faktly\LaravelPrometheusMetrics\Tests;

class ExampleTest extends TestCase
{
    /** @test */
    public function it_does_something()
    {
        $this->assertTrue(true);
    }
}
```

## Code Style

```bash
composer lint:check         # Check
composer lint               # Fix automatically
composer psalm              # Static analysis
```

## Development Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/faktly/laravel-prometheus-metrics.git
   cd laravel-prometheus-metrics
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Create a feature branch:
   ```bash
   git checkout -b feature/amazing-feature
   ```
5. Make your changes and commit:
   ```bash
   git add .
   git commit -m "✨ Add amazing feature"
   ```
6. Push to your fork:
   ```bash
   git push origin feature/amazing-feature
   ```
7. Open a Pull Request

### Test and run in a local laravel project

1. Create a new project
   ```bash
   laravel new laravel-packages
   mkdir -p laravel-packages/packages/faktly
   cd laravel-packages/packages/faktly
   git clone https://github.com/faktly/laravel-prometheus-metrics.git
   cd laravel-prometheus-metrics
   ```
2. Add local repository within laravel project composer.json
    ```json
    "repositories": [{
      "type": "path",
      "url": "packages/faktly/laravel-prometheus-metrics"
    }],
    ```
3. Install local package into laravel application
    ```bash
   composer require faktly/laravel-prometheus-metrics @dev
    ```
4. Continue with 3. from [Development Setup](#development-setup)

## Pull Request Process

1. Update README.md with new features or options
2. Update CHANGELOG.md under "Unreleased" section. Ensure version is not being added to composer.json
3. Ensure all tests pass: `composer test`
4. Ensure code style passes: `composer lint:check`
5. Ensure static analysis passes: `composer psalm`
6. PR title should describe what it does
7. PR description should reference related issues

### Example CHANGELOG.md entry:

```markdown
## [Unreleased]

### Added
- New feature description

### Fixed
- Bug fix description

### Changed
- Breaking change description
```

## Questions?

Open an issue or reach out to maintainers.

## License

By contributing, you agree your contributions are licensed under MIT License. See [LICENSE.md](LICENSE.md)

## Recognition

Contributors recognized in [CHANGELOG.md](CHANGELOG.md) and [README.md!(README.md)

Thank you for contributing! 🎉
