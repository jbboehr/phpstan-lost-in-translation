# Getting started

## Installation

The `0.x` series remains experimental while its public configuration and diagnostics settle. Install the `0.1` release
line so later minor releases do not introduce unexpected pre-1.0 compatibility changes:

```shell
composer require --dev jbboehr/phpstan-lost-in-translation:^0.1
```

When [phpstan/extension-installer](https://github.com/phpstan/extension-installer) is installed, Composer registers the
extension automatically. Otherwise, include it explicitly from `phpstan.neon`:

```neon
includes:
    - vendor/jbboehr/phpstan-lost-in-translation/extension.neon
```

Core PHP translation-call analysis works without any Laravel-specific PHPStan extension. Two integrations provide more
coverage:

- [Larastan](https://github.com/larastan/larastan) improves Laravel-aware type inference.
- [Bladestan](https://github.com/bladestan/bladestan) enables translation analysis inside reachable Blade templates.

## Compatibility

The validation matrix covers PHPStan 1.12 and 2.x with these Laravel and PHP combinations:

| Laravel | Tested PHP versions |
| --- | --- |
| 9 | 8.1–8.2 |
| 10 | 8.1–8.3 |
| 11 | 8.2–8.5 |
| 12 | 8.2–8.5 |
| 13 | 8.3–8.5 |

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

## Type inference

Translation keys do not have to be literal strings at the call site. If PHPStan can infer a finite set of possible
string values, Lost in Translation checks each value:

```php
$key = 'foo';
__($key);

foreach (['foo', 'bar'] as $key) {
    __($key);
}

/** @return "foo"|"bar" */
function getKey(): string {}
__(getKey());

const KEY = 'foo';
__(KEY);

/** @var array{foo: mixed, bar: mixed} $map */
foreach ($map as $key => $value) {
    __($key);
}
```

## Adoption

Start with the defaults. They enable the higher-confidence missing-key, replacement, choice, locale, encoding, and
translation-file checks. After addressing those findings, consider enabling `unusedTranslationStrings`,
`disallowDynamicTranslationStrings`, and `requireCompletePluralForms` one at a time. These checks are disabled by
default because existing applications often need an initial cleanup or a deliberate dynamic-key policy.

See the [configuration reference](configuration.md) for every option.
