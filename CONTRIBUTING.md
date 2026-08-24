# Contributing

Thanks for taking the time to contribute!

## Getting started

```bash
git clone https://github.com/maciej-kosiedowski/IndexNow-for-Laravel.git
cd IndexNow-for-Laravel
composer install
```

A coverage driver (`pcov` or `xdebug`) is required for the coverage and mutation testing steps; the
plain test suite runs without one.

### Working against a local copy of the core package

The package depends on [`slimad/indexnow`](https://github.com/maciej-kosiedowski/IndexNow). To test
against a checkout of it rather than the released version:

```bash
composer config repositories.slimad-indexnow \
    '{"type":"path","url":"../IndexNow","options":{"versions":{"slimad/indexnow":"1.0.0"}}}'
composer update slimad/indexnow
```

Remove the `repositories` entry from `composer.json` before committing — it must not ship in a
published package.

## Quality gate

Every pull request has to pass the same gate that CI runs:

```bash
composer cs         # Laravel Pint
composer phpstan    # Larastan level 8
composer test       # PHPUnit against Testbench
composer infection  # mutation testing
```

or simply:

```bash
composer ci
```

`composer cs:fix` rewrites the files that violate the coding standard.

To run a single test file or a single test:

```bash
vendor/bin/phpunit tests/Feature/IndexNowManagerTest.php
vendor/bin/phpunit --filter test_flush_discards_urls_from_another_host
```

## Non-negotiables

* **100% line coverage**, enforced by `bin/check-coverage.php` in CI. New behaviour needs tests.
* **Mutation score at or above the threshold in `infection.json5`** (currently 95%). A surviving
  mutant means a test is not specific enough — tighten the assertion rather than lowering the bar.
* **Larastan level 8**, no baseline, no `@phpstan-ignore`.
* **No new runtime dependencies** beyond `illuminate/*` and `slimad/indexnow` without discussing it
  first.
* **Protocol changes belong in the core package.** This repository only holds Laravel glue.

## Supported versions

Pull requests have to work on every supported combination:

| Laravel | PHP |
| ------- | --- |
| 11 | 8.2, 8.3, 8.4 |
| 12 | 8.2, 8.3, 8.4 |
| 13 | 8.3, 8.4 |

Avoid framework APIs that only exist in the newest release; when you cannot, guard them.

## Pull requests

1. Branch off `master`.
2. Keep the change focused.
3. Add a `CHANGELOG.md` entry under `## [Unreleased]`.
4. Document new configuration keys in `config/indexnow.php` **and** in the README table.
5. Make sure `composer ci` is green locally before pushing.

## Reporting bugs

Open an issue with the package version, the Laravel and PHP versions, the store driver and a minimal
reproducer. Never include your IndexNow key. Security problems go through the process described in
[SECURITY.md](SECURITY.md) instead.
