
# phpstan-lost-in-translation

[![ci](https://github.com/jbboehr/phpstan-lost-in-translation/actions/workflows/ci.yml/badge.svg)](https://github.com/jbboehr/phpstan-lost-in-translation/actions/workflows/ci.yml)
[![License: AGPL v3+](https://img.shields.io/badge/License-AGPL_v3%2b-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
![stability-experimental](https://img.shields.io/badge/stability-experimental-orange.svg)

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

## Type inference

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
strings may still need to be specified even in the base locale. Currently, this check reports untranslated
strings in the base locale where the group and translation key are identifiers, where an identifier matches
`[\w][\w\d]*(?:[_-][\w][\w\d]*)*`. For example: `group-name.translation-key`. **Enabled by default**

```neon
parameters:
    lostInTranslation:
        missingTranslationStringsInBaseLocale: true
```

```php
<?php

__('foo.bar', [], 'en');
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
         🪪  lostInTranslation.unusedReplacement
         💡 Locale: "en", Key: "exists in all locales", Value: "exists in all locales"
  4      Unused translation replacement: "foo"
         🪪  lostInTranslation.unusedReplacement
         💡 Locale: "en", Key: "exists in all locales", Value: "exists in all locales"
  7      Replacement string matches multiple variants: "foo"
         🪪  lostInTranslation.multipleReplaceVariants
         💡 Locale: "en", Key: ":foo :FOO", Value: ":foo :FOO"
 ------ -------------------------------------------------------------------------------
```

### Analyze choices

Choices will be analyzed for potentially invalid options. **Enabled by default.**

```neon
parameters:
    lostInTranslation:
        invalidChoices: true
```

```php
<?php

trans_choice('{0} There are none|{1} There is one|[2] There are :count', 3, [], 'en');
```

```console
$ phpstan analyse --configuration=e2e/phpstan-e2e.neon --no-progress -v e2e/src/invalid-choice.php
 ------ ------------------------------------------------------------------------------------------------------------------
  Line   invalid-choice.php
 ------ ------------------------------------------------------------------------------------------------------------------
  3      Translation choice does not cover all possible cases for number of type: 3
         🪪  lostInTranslation.choiceMissingCase
         💡 Locale: "en", Key: "{0} There are none|{1} There is one|[2] There are :count", Value: "{0} There are none|{1}
            There is one|[2] There are :count"
 ------ ------------------------------------------------------------------------------------------------------------------
```

### Errors in translation files

Errors in translation lines will be logged as well, including parse errors. **Enabled by default**.

```neon
parameters:
    lostInTranslation:
        translationLoaderWarnings: true
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

If `strictLocale` is set, it must match, for example, `pt_BR`.
Otherwise, it can also match any of `PT_br`, `pt-br`, etc. **Disabled by default.**

```neon
parameters:
    lostInTranslation:
        invalidLocales: true
        strictLocales: true
```

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
         🪪  lostInTranslation.unknownLocale
 ------ --------------------------------------------------------------------------

 ------ -----------------------------------------------------------------
  Line   invalid-locale.php
 ------ -----------------------------------------------------------------
  3      Locale has no available translation strings: invalid_locale
         🪪  lostInTranslation.noLocaleTranslations
  3      Unknown locale: invalid_locale
         🪪  lostInTranslation.unknownLocale
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
  3      Invalid character encoding for value: "messages.\xf0(\x8c\xbc"
         🪪  lostInTranslation.invalidCharacterEncoding
 ------ --------------------------------------------------------------------------------

 ------ ------------------------------------------------------------------------------
  Line   invalid-character-encodings.php
 ------ ------------------------------------------------------------------------------
  3      Invalid character encoding for key "messages.\xf0(\x8c\xbc"
         🪪  lostInTranslation.invalidCharacterEncoding
  3      Invalid character encoding for value "messages.\xf0(\x8c\xbc" in locale "ja"
         🪪  lostInTranslation.invalidCharacterEncoding
 ------ ------------------------------------------------------------------------------
```

## Configuration

```neon
parameters:
    lostInTranslation:
        # should translation keys with types not statically known be allowed?
        disallowDynamicTranslationStrings: false
        # strings in the base locale won't be reported as missing, unless they contain a group. May use value set in Laravel if unconfigured.
        baseLocale: null
        # the path to your language directory if not `./lang`. May use value set in Laravel if unconfigured.
        langPath: null
        # issue errors for invalid character encodings
        invalidCharacterEncodings: true
        # should we analyze choices for invalid values?
        invalidChoices: true
        # warn on locales that have no translation strings or are invalid locale identifiers
        invalidLocales: true
        # should we analyze translation replacements for invalid values?
        invalidReplacements: true
        # look for missing translation strings? (main feature)
        missingTranslationStrings: true
        # report translation strings in the base locale that might be missing a translation (usually in `lang/*/*.php`)
        missingTranslationStringsInBaseLocale: true
        # allow more flexible locale identifiers
        strictLocales: false
        # aggregate used translations and diff with the full locale database to detect potentially unused translations
        unusedTranslationStrings: false
```

## References

This project is based on and inspired by [coding-socks/lost-in-translation](https://github.com/coding-socks/lost-in-translation).

## License

This project is licensed under the [AGPL v3+](https://www.gnu.org/licenses/agpl-3.0) License - see the LICENSE.md file for details.
