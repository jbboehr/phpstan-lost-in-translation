# phpstan-lost-in-translation

[![ci](https://github.com/jbboehr/phpstan-lost-in-translation/actions/workflows/ci.yml/badge.svg)](https://github.com/jbboehr/phpstan-lost-in-translation/actions/workflows/ci.yml)
[![License: AGPL-3.0-only WITH romic-exception](https://img.shields.io/badge/license-AGPL--3.0--only%20WITH%20romic--exception-blue.svg)](LICENSE.md)
![stability-experimental](https://img.shields.io/badge/stability-experimental-orange.svg)
<!-- agent-badge:start -->[![AI burn](https://img.shields.io/endpoint?url=https%3A%2F%2Fgist.githubusercontent.com%2Fjbboehr%2F1238801db4d132c97c1f32346be14450%2Fraw%2Fagent-badge.json&cacheSeconds=300)](https://github.com/arlegotin/agent-badge)<!-- agent-badge:end -->

A PHPStan extension for statically checking Laravel translations. It finds missing and possibly unused translation
keys, validates replacements, plural choices, locales, encodings, and translation files, and can inspect calls in Blade
templates through Bladestan.

## What it catches

- missing translations, including likely omissions from the base locale;
- translation keys that are unused or cannot be inferred statically;
- unused replacements and replacement keys that match multiple casing variants;
- malformed or incomplete plural choices;
- unknown locales, locale conflicts, and invalid character encodings; and
- invalid translation-file values and parse failures.

## Installation

This project has not published its first stable release. While it remains experimental, install the current development
branch explicitly:

```shell
composer require --dev jbboehr/phpstan-lost-in-translation:dev-develop
```

When [phpstan/extension-installer](https://github.com/phpstan/extension-installer) is installed, Composer registers the
extension automatically. Otherwise, include it explicitly from `phpstan.neon`:

```neon
includes:
    - vendor/jbboehr/phpstan-lost-in-translation/extension.neon
```

## Quick example

Calls with statically inferable keys are checked against the application's translation files.

<!-- akashi-example: missing-translation -->

```php
<?php

__('missing translation string');
```

```console
$ phpstan analyse
 ------ -------------------------------------------------------------------------
  Line   example.php
 ------ -------------------------------------------------------------------------
  3      Missing translation string "missing translation string" for locales: ja
         🪪  lostInTranslation.missingTranslationString
 ------ -------------------------------------------------------------------------
```

## Compatibility

The validation matrix covers PHPStan 1.12 and 2.x with these Laravel and PHP combinations:

| Laravel | Tested PHP versions |
| --- | --- |
| 9 | 8.1–8.2 |
| 10 | 8.1–8.3 |
| 11 | 8.2–8.5 |
| 12 | 8.2–8.5 |
| 13 | 8.3–8.5 |

## Recommended integrations

Core PHP translation-call analysis does not require either integration:

- [Larastan](https://github.com/larastan/larastan) improves Laravel-aware type inference.
- [Bladestan](https://github.com/bladestan/bladestan) enables translation analysis inside reachable Blade templates.

## Supported translation APIs

| Form | Support |
| --- | --- |
| `__('key')` | Supported |
| `trans('key')` | Supported |
| `trans_choice('key', $count)` | Supported |
| `$translator->get('key')` | Supported when PHPStan infers Laravel's translator contract |
| `$translator->choice('key', $count)` | Supported when PHPStan infers Laravel's translator contract |
| `Lang::get('key')` | Supported |
| `Lang::choice('key', $count)` | Supported |
| Blade `__()` and `@lang` | Supported through Bladestan |

## Configuration

The defaults enable higher-confidence missing-key, replacement, choice, locale, encoding, and translation-file checks.
After addressing those findings, stricter checks can be enabled independently:

```neon
parameters:
    lostInTranslation:
        unusedTranslationStrings: true
        disallowDynamicTranslationStrings: true
        requireCompletePluralForms: true
```

See the [configuration reference](docs/usage/configuration.md) for every option and default.

## Documentation

- [Getting started](docs/usage/getting-started.md) covers installation, compatibility, recognized APIs, and adoption.
- [Translation keys](docs/usage/translation-keys.md) covers missing, unused, base-locale, dynamic, and fuzzy checks.
- [Blade templates](docs/usage/blade.md) covers Bladestan and the nested-analysis compatibility bridges.
- [Replacements and plural choices](docs/usage/replacements-and-choices.md) defines replacement and choice validation.
- [Locales and translation files](docs/usage/locales-and-files.md) covers locale policy, layouts, encoding, and loader errors.
- [Configuration](docs/usage/configuration.md) lists every supported option and default.

## Development

Enter the default PHP 8.1 shell and use an ordinary mutable Composer installation:

```console
nix develop
composer install
vendor/bin/phpunit
vendor/bin/phpstan
```

Run the complete routine suite, including the supported PHP/Laravel matrix and isolated consumers, with:

```console
nix flake check --keep-going -L
```

Build and verify the user guide from the PHP 8.2 documentation shell:

```console
nix develop .#documentation --command composer docs:check
```

See [CONTRIBUTING.md](CONTRIBUTING.md) and the [mutation-testing guide](docs/mutation-testing.md) for the remaining
development workflows.

## References

This project is based on and inspired by
[coding-socks/lost-in-translation](https://github.com/coding-socks/lost-in-translation).

## License

phpstan-lost-in-translation is licensed under the **GNU Affero General Public License version 3 with the Romic
Exception**: `AGPL-3.0-only WITH romic-exception`.

The Romic Exception permits phpstan-lost-in-translation to be linked or combined with other code without subjecting
that other code to the AGPL merely because of the linking or combination. Modifications to the covered project remain
subject to the Project License, including its source-availability requirements for modified versions made available
over a computer network. See [LICENSE.md](LICENSE.md) and [docs/LICENSE_EXCEPTION.md](docs/LICENSE_EXCEPTION.md).

Contribution terms and the optional CLA route are documented in [CONTRIBUTING.md](CONTRIBUTING.md). Alternative licenses
may be available from the [Project Steward](docs/STEWARD.md).
