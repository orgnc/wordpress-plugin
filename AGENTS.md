# AGENTS.md

Guidance for agents working in this repository.

## Project Shape

This is the Organic WordPress plugin. The plugin source lives under `src/`; the production zip is assembled into `build/organic` by `build-zip.sh`.

Important entry points:

- `src/organic.php` is the WordPress plugin bootstrap.
- `src/Organic/Organic.php` wires most runtime services and hooks.
- `src/Organic/SDK/OrganicSdk.php` contains Organic Platform API calls.
- `src/readme.txt` is the WordPress.org plugin readme.
- `README.md` is developer-facing repo documentation.

## Versioning

Do not hardcode release versions into `src/organic.php` or the readme stable tag. They intentionally use `ORGANIC_PLUGIN_VERSION_VALUE`.

`build-zip.sh` replaces `ORGANIC_PLUGIN_VERSION_VALUE` with the build number:

- GitHub release builds pass the release tag as `BUILD_NUMBER`.
- PR/dev builds use `dev-<PR number>`.

When adding changelog entries, add them under `== Changelog ==` in `src/readme.txt`. The latest semver tag seen in this repo was `1.16.0`; release-drafter defaults to a minor bump unless labels override it.

## Development Commands

From the repo root:

```bash
poetry run ./dev.py up
poetry run ./dev.py lint
poetry run ./dev.py build-zip
```

From `src/` after Composer dependencies are installed:

```bash
composer install --no-interaction
vendor/bin/phpunit -c ./phpunit.xml --exclude-group selenium_test
php -d error_reporting='E_ALL & ~E_DEPRECATED' vendor/bin/phpcs --standard=./phpcs.xml <files>
```

The full PHPUnit suite includes Selenium tests. Those need WebDriver and environment credentials, so for ordinary PHP changes use `--exclude-group selenium_test` unless you are explicitly working on browser tests.

PHPCS/WPCS is old enough to emit PHP deprecation noise on newer PHP versions. Suppress `E_DEPRECATED` when running PHPCS so actual coding-standard failures are visible.

## Release Build

`build-zip.sh` copies only selected files into `build/organic`, runs `composer install --no-dev`, builds block assets under `src/blocks`, and zips the result.

If you add a new runtime PHP class under `src/Organic`, Composer PSR-4 autoloading should include it automatically.

## Git / Editing

The working tree may already contain user changes. Do not revert changes you did not make.

Use `apply_patch` for manual edits. Avoid broad refactors unless needed for the requested change.

Before finalizing code changes, run focused syntax checks and relevant tests. Mention any tests that could not be run and why.
