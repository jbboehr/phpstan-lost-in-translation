
# phpstan-lost-in-translation

[![ci](https://github.com/jbboehr/phpstan-lost-in-translation/actions/workflows/ci.yml/badge.svg)](https://github.com/jbboehr/phpstan-lost-in-translation/actions/workflows/ci.yml)
[![License: AGPL-3.0-only WITH romic-exception](https://img.shields.io/badge/license-AGPL--3.0--only%20WITH%20romic--exception-blue.svg)](LICENSE.md)
![stability-experimental](https://img.shields.io/badge/stability-experimental-orange.svg)
<!-- agent-badge:start -->[![AI burn](https://img.shields.io/endpoint?url=https%3A%2F%2Fgist.githubusercontent.com%2Fjbboehr%2F1238801db4d132c97c1f32346be14450%2Fraw%2Fagent-badge.json&cacheSeconds=300)](https://github.com/arlegotin/agent-badge)<!-- agent-badge:end -->

## Installation

To use this extension, require it in [Composer](https://getcomposer.org/):

```bash
composer require --dev jbboehr/phpstan-lost-in-translation
```

If you also install [phpstan/extension-installer](https://github.com/phpstan/extension-installer) then you're all set!

### Manual installation

If you don't want to use `phpstan/extension-installer`, include `extension.neon` in your project's PHPStan config:

```neon
includes:
    - vendor/jbboehr/phpstan-lost-in-translation/extension.neon
```

## Additional Requirements

While there is not a strict requirement, this extension will likely not function as expected without the
following extra PHPStan extensions installed:

* [Larastan](https://github.com/larastan/larastan) - Provides better type inference for Laravel applications
* [Bladestan](https://github.com/bladestan/bladestan) - Provides static analysis of Blade templates

## Features

### Type inference

Note that for most of the features below, we can only analyze any potential constant strings in the type of the
variable passed into the translation function.
**This takes advantage of [PHPStan](https://phpstan.org/)'s type inference.**
For example, these should all be able to be analyzed correctly:

```php
$key = 'foo';
__($key);

foreach (['foo', 'bar'] as $key) {
    __($key);
}

// this one seems to not be working atm :shrug:
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

### Find missing translation strings

Your application's source files will be scanned for calls to the Laravel translator and checked for undefined
translation strings. **Enabled by default.**

```neon
parameters:
    lostInTranslation:
        missingTranslationStrings: true
```

<!-- akashi-example: missing-translation -->

```php
<?php

__('missing translation string');
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v e2e/src/missing-translation-string.php
 ------ -------------------------------------------------------------------------
  Line   missing-translation-string.php
 ------ -------------------------------------------------------------------------
  3      Missing translation string "missing translation string" for locales: ja
         🪪  lostInTranslation.missingTranslationString
 ------ -------------------------------------------------------------------------
```

If [Larastan](https://github.com/larastan/larastan) is installed, there will be better type inference. If
[Bladestan](https://github.com/bladestan/bladestan) is installed, it will be possible to inspect blade templates
(you probably really want this).

Bladestan runs compiled templates through a nested PHPStan analysis. By default,
this extension bridges its diagnostics back through the outer analysis so error
identifiers, translation metadata, tips, and Bladestan's template path/line
metadata are preserved. Set `bridgeBladeDiagnostics: false` only if this
compatibility bridge conflicts with another extension or a future Bladestan
release.

```php
<?php

view('sample', [
    'var' => 'val'
]);
```

```bladehtml
@lang('blade at directive')
{{ __('blade double underscore') }}
{{ __('exists in all locales') }}
{{ __('only in ja') }}

@php
    // these may require larastan to work
    app('translator')->get('via app function');
    \Illuminate\Support\Facades\App::make('translator')->get('via app facade');
    app(\Illuminate\Translation\Translator::class)->get('via app function with class');
@endphp
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress --error-format=blade -v e2e/src/blade.php
 ------ --------------------------------------------------------------------------
  Line   e2e/src/blade.php
 ------ --------------------------------------------------------------------------
  3      Missing translation string "blade at directive" for locales: ja
         rendered in: sample.blade.php:1
  3      Missing translation string "blade double underscore" for locales: ja
         rendered in: sample.blade.php:2
  3      Missing translation string "exists in all locales" for locales: ja
         rendered in: sample.blade.php:3
  3      Missing translation string "only in ja" for locales: ja
         rendered in: sample.blade.php:4
  3      Missing translation string "via app facade" for locales: ja
         rendered in: sample.blade.php:9
  3      Missing translation string "via app function with class" for locales: ja
         rendered in: sample.blade.php:10
  3      Missing translation string "via app function" for locales: ja
         rendered in: sample.blade.php:8
 ------ --------------------------------------------------------------------------
```

### Find unused translations

We can attempt to detect unused translation strings. **Disabled by default.**

```neon
parameters:
    lostInTranslation:
        unusedTranslationStrings: true
```

```json
{
    "this string is not used anywhere": "this string is not used anywhere"
}
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v
 ------ --------------------------------------------------------------------------------------
  Line   lang/ja.json
 ------ --------------------------------------------------------------------------------------
  2      Possibly unused translation string "this string is not used anywhere" for locale: ja
         🪪  lostInTranslation.possiblyUnusedTranslationString
 ------ --------------------------------------------------------------------------------------
```

### Disallow dynamic translations strings

We can disallow using translations strings that are not statically known. **Disabled by default.**

```neon
parameters:
    lostInTranslation:
        disallowDynamicTranslationStrings: true
```

<!-- akashi-example: dynamic-translation -->

```php
<?php

/** @var \Illuminate\Contracts\Translation\Translator $translator */
/** @var string $dynamic */
$translator->get($dynamic);

/** @var "foo"|"bar"|\Exception $craycray */
$translator->get($craycray);
```

```console
phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v e2e/src/dynamic-translation-string.php
 ------ ----------------------------------------------------------------------
  Line   dynamic.php
 ------ ----------------------------------------------------------------------
  5      Disallowed dynamic translation string of type: string
         🪪  lostInTranslation.dynamicTranslationString
  8      Disallowed dynamic translation string of type: 'bar'|'foo'|Exception
         🪪  lostInTranslation.dynamicTranslationString
 ------ ----------------------------------------------------------------------
```

### Find strings untranslated in the base locale

Missing translation strings in the base locale are not reported as missing. However, some translation
strings may still need to be specified even in the base locale. This check reports untranslated strings where
the group and every dot-separated key segment are identifiers matching `[\w][\w\d]*(?:[_-][\w][\w\d]*)*`.
For example: `group-name.translation-key`, `validation.custom.email.required`, or
`package::group.nested.translation-key`. Calls without an explicit locale include the configured base locale
even when it has no translation file. **Enabled by default**

```neon
parameters:
    lostInTranslation:
        missingTranslationStringsInBaseLocale: true
```

<!-- akashi-example: missing-base-translation -->

```php
<?php

__('foo.bar');
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v e2e/src/missing-translation-string-in-base-locale.php
 ------ -----------------------------------------------------------------
  Line   missing-translation-string-in-base-locale.php
 ------ -----------------------------------------------------------------
  3      Likely missing translation string "foo.bar" for base locale: en
         🪪  lostInTranslation.missingBaseLocaleTranslationString
 ------ -----------------------------------------------------------------
```

### Analyze replacements

Replacements will be analyzed for undesirable behavior. **Enabled by default.**

```neon
parameters:
    lostInTranslation:
        invalidReplacements: true
```

<!-- akashi-example: invalid-replacements -->

```php
<?php

/* has a replacement that doesn't exist in the translation key */
__('exists in all locales', ['foo' => 'bar', 'bar' => 'bat'], 'en');

/* has multiple replacement variants */
__(':foo :FOO', ['foo' => 'bar'], 'en');
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v e2e/src/invalid-replacement.php
 ------ -------------------------------------------------------------------------------
  Line   invalid-replacement.php
 ------ -------------------------------------------------------------------------------
  4      Unused translation replacement: "bar"
         🪪  lostInTranslation.invalidReplacement.unused
         💡 Locale: "en", Key: "exists in all locales", Value: "exists in all locales"
  4      Unused translation replacement: "foo"
         🪪  lostInTranslation.invalidReplacement.unused
         💡 Locale: "en", Key: "exists in all locales", Value: "exists in all locales"
  7      Replacement string matches multiple variants: "foo"
         🪪  lostInTranslation.invalidReplacement.multipleVariants
         💡 Locale: "en", Key: ":foo :FOO", Value: ":foo :FOO"
 ------ -------------------------------------------------------------------------------
```

### Analyze choices

Choices will be analyzed for potentially invalid options. Syntax validation and explicit-condition completeness checking
are **enabled by default**. Disable only the explicit-condition warning with `requireCompleteChoiceCoverage: false`;
malformed conditions and invalid bounds will still be reported while `invalidChoices` remains enabled.

For stricter translation-quality checking, enable `requireCompletePluralForms`. This opt-in warning reports an
unconditioned choice when it provides fewer positional forms than the locale can select. It uses the configured
`localeAliases` target for the plural policy, retains the application's locale in the diagnostic, and checks a
full-sentence key as the source value when no separate translation value exists. Missing grouped keys are left to the
missing-translation diagnostics because the key is not plural content. Locale spelling is matched exactly as Laravel
matches it; an unaliased locale absent from Laravel's selector table uses the first form only. Laravel's valid first-form
fallback remains accepted when the option is off. The diagnostic identifier is
`lostInTranslation.invalidChoice.missingPluralForm`.

```neon
parameters:
    lostInTranslation:
        invalidChoices: true
        requireCompleteChoiceCoverage: true
        requireCompletePluralForms: false
```

<!-- akashi-example: invalid-choice -->

```php
<?php

trans_choice('{0} There are none|{1} There is one|[2] There are :count', 3, [], 'en');
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v e2e/src/invalid-choice.php
 ------ ------------------------------------------------------------------------------------------------------------------
  Line   invalid-choice.php
 ------ ------------------------------------------------------------------------------------------------------------------
  3      Explicit translation choice conditions do not cover all possible cases for number of type: 3
         🪪  lostInTranslation.invalidChoice.missingCase
         💡 Locale: "en", Key: "{0} There are none|{1} There is one|[2] There are :count", Value: "{0} There are none|{1}
            There is one|[2] There are :count"
 ------ ------------------------------------------------------------------------------------------------------------------
```

### Errors in translation files

Errors in translation lines will be logged as well, including parse errors. **Enabled by default**.

```neon
parameters:
    lostInTranslation:
        translationLoaderErrors: true
```

```json
{
  "this value is not allowed": 2
}
```

```php
<?php return [
    'this_value_is_not_allowed' => 3,
];
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v
 ------ ------------------------------------------------
  Line   lang/zh.json
 ------ ------------------------------------------------
  2      Invalid value: 2
         🪪  lostInTranslation.translationLoaderError
 ------ ------------------------------------------------

 ------ ------------------------------------------------
  Line   lang/zh/messages.php
 ------ ------------------------------------------------
  2      Invalid value: 3
         🪪  lostInTranslation.translationLoaderError
 ------ ------------------------------------------------
```

### Analyze locales

If an invalid locale is given to a translation function, an error will be emitted. **Enabled by default.**

If `strictLocales` is set, locale identifiers used in calls and translation
paths must match exactly, for example, `pt_BR`. Otherwise, validation and
translation lookup treat forms such as `PT_br`, `pt-br`, and `pt_BR` as the
same locale. Script subtags are normalized separately, so forms such as
`ZH-hANS` and `zh_Hans` also resolve to the same locale. **Disabled by
default.**

If two translation paths resolve to the same flexible locale identifier, the
scanner reports a conflict and loads the first spelling in deterministic path
order. Strict mode treats the spellings as separate locales.

Application-specific locale names can be mapped to a locale known by Symfony
Intl for validation. The alias does not redirect translation lookup: language
files and calls continue to use the application locale key. Alias keys follow
the same flexible or strict matching policy as other locale identifiers. Each
target must be a locale known directly to Symfony Intl; targets are not
resolved through other aliases, and two keys may not resolve to the same
flexible locale identifier.

```neon
parameters:
    lostInTranslation:
        invalidLocales: true
        localeAliases:
            de_informal: de_DE
        strictLocales: true
```

<!-- akashi-example: invalid-locale -->

```php
<?php

__('foobar', [], 'invalid_locale');
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon -v --no-progress e2e/src/invalid-locale.php
 ------ --------------------------------------------------------------------------
  Line   e2e/lang/fake.json
 ------ --------------------------------------------------------------------------
  -1     Unknown locale: fake
         🪪  lostInTranslation.invalidLocale.unknown
 ------ --------------------------------------------------------------------------

 ------ -----------------------------------------------------------------
  Line   invalid-locale.php
 ------ -----------------------------------------------------------------
  3      Locale has no available translation strings: invalid_locale
         🪪  lostInTranslation.invalidLocale.noTranslations
  3      Missing translation string "foobar" for locales: invalid_locale
         🪪  lostInTranslation.missingTranslationString
  3      Unknown locale: invalid_locale
         🪪  lostInTranslation.invalidLocale.unknown
 ------ -----------------------------------------------------------------
```

### Invalid character encoding

If a translation string is not valid UTF-8, an error will be issued. **Enabled by default.**

```neon
parameters:
    lostInTranslation:
        invalidCharacterEncodings: true
```

```php
<?php return [
  "\xf0\x28\x8c\xbc" => "\xf0\x28\x8c\xbc",
];

```

<!-- akashi-example: invalid-character-encoding-call -->

```php
<?php

__("messages.\xf0\x28\x8c\xbc", [], 'ja');
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v e2e/src/invalid-character-encodings.php
 ------ --------------------------------------------------------------------------------
  Line   e2e/lang/ja/messages.php
 ------ --------------------------------------------------------------------------------
  3      Invalid character encoding for key: "messages.\xf0(\x8c\xbc"
         🪪  lostInTranslation.invalidCharacterEncoding
  3      Invalid character encoding for value: "\xf0(\x8c\xbc"
         🪪  lostInTranslation.invalidCharacterEncoding
 ------ --------------------------------------------------------------------------------

 ------ ------------------------------------------------------------------------------
  Line   invalid-character-encodings.php
 ------ ------------------------------------------------------------------------------
  3      Invalid character encoding for key "messages.\xf0(\x8c\xbc"
         🪪  lostInTranslation.invalidCharacterEncoding
  3      Invalid character encoding for value "\xf0(\x8c\xbc" in locale "ja"
         🪪  lostInTranslation.invalidCharacterEncoding
 ------ ------------------------------------------------------------------------------
```

## Configuration

```neon
parameters:
    lostInTranslation:
        # preserve translation identifiers, metadata, tips, and template locations across Bladestan's nested analysis
        bridgeBladeDiagnostics: true
        # should translation keys with types not statically known be allowed?
        disallowDynamicTranslationStrings: false
        # strings in the base locale won't be reported as missing, unless they contain a group. May use value set in Laravel if unconfigured.
        baseLocale: null
        # the path to your language directory if not `./lang`. May use value set in Laravel if unconfigured.
        langPath: null
        # validate application-specific locale keys through locales known by Symfony Intl without changing lookup keys
        localeAliases: []
        # issue errors for invalid character encodings
        invalidCharacterEncodings: true
        # should we analyze choices for invalid values?
        invalidChoices: true
        # require explicit choice conditions to cover every possible value of the inferred number type?
        requireCompleteChoiceCoverage: true
        # require unconditioned choices to provide every positional plural form the locale can select?
        requireCompletePluralForms: false
        # warn on locales that have no translation strings or are invalid locale identifiers
        invalidLocales: true
        # should we analyze translation replacements for invalid values?
        invalidReplacements: true
        # look for similar keys? might want to disable this, a bit slow
        fuzzySearch: true
        # look for missing translation strings? (main feature)
        missingTranslationStrings: true
        # report translation strings in the base locale that might be missing a translation (usually in `lang/*/*.php`)
        missingTranslationStringsInBaseLocale: true
        # allow more flexible locale identifiers
        strictLocales: false
        # aggregate used translations and diff with the full locale database to detect potentially unused translations
        unusedTranslationStrings: false
```

The translation scanner currently supports these layouts inside `langPath`:

- `<locale>.json`
- `<locale>/<group>.php`
- `vendor/<namespace>/<locale>/<group>.php`

Other nested PHP and JSON layouts are ignored. Vendor JSON catalogues and PHP
files nested below the group level are not part of Laravel's vendor override
layout and remain unsupported.

## Development

Enter the default PHP 8.1 development shell, then use an ordinary mutable
Composer installation for interactive work:

```console
nix develop
composer install
vendor/bin/phpunit
vendor/bin/phpstan
```

Run the complete routine validation suite, including the supported PHP and
Laravel matrix and isolated consumers, with:

```console
nix flake check --keep-going -L
```

These checks install dependencies from Nix-managed fixed-output Composer
repositories; they do not read the checkout's `vendor/`. GitHub runs the same
checks as separate Nix jobs and also retains a small independent PHP 8.4
baseline using `setup-php` and Composer.

Mutation testing is an explicit Nix target and is not part of `nix flake
check`:

```console
nix build .#mutation -L
```

The PHP 8.4 `mutation` development shell remains available for focused local
campaigns. See the [mutation-testing guide](docs/mutation-testing.md) for the
baseline policy.

Run the PHPUnit suite with PCOV and regenerate the Clover report locally with:

```console
composer coverage
```

README translation-call examples are verified with Akashi in the PHP 8.2 documentation shell:

```console
nix develop .#documentation --command composer docs:check
```

## References

This project is based on and inspired by [coding-socks/lost-in-translation](https://github.com/coding-socks/lost-in-translation).

## License

phpstan-lost-in-translation is licensed under the **GNU Affero General Public License version 3 with the Romic
Exception**:

```text
AGPL-3.0-only WITH romic-exception
```

The Romic Exception permits phpstan-lost-in-translation to be linked or combined with other code without subjecting
that other code to the AGPL merely because of the linking or combination. Modifications to the covered project remain
subject to the Project License, including its source-availability requirements for modified versions made available
over a computer network.

See [LICENSE.md](LICENSE.md) and [docs/LICENSE_EXCEPTION.md](docs/LICENSE_EXCEPTION.md) for the complete terms.

Contributions are accepted under the terms in [CONTRIBUTING.md](CONTRIBUTING.md). Unless a contributor elects the CLA
route, each contribution is offered under `AGPL-3.0-only WITH romic-exception OR Apache-2.0`, at each recipient's
option, while the public project incorporates it under the Project License. The Apache-2.0 alternative applies only to
the contributor-authored portions and does not make the project as a whole available under Apache-2.0.

A contributor may instead elect [the CLA](docs/CLA-v1.md), keeping the contribution publicly under the Project License
while granting the [Project Steward](docs/STEWARD.md) the additional rights specified there.

Alternative licenses may be available from the [Project Steward](docs/STEWARD.md).
