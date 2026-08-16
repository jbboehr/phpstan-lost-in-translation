# Locales and translation files

## Locale validation

`invalidLocales` is enabled by default. It reports locale identifiers unknown to Symfony Intl and locales with no
available translation strings.

By default, validation and lookup treat forms such as `PT_br`, `pt-br`, and `pt_BR` as the same locale. Script subtags
are normalized separately, so `ZH-hANS` and `zh_Hans` also resolve to the same locale. Set `strictLocales: true` to
require exact identifiers such as `pt_BR` in calls and translation paths.

If two paths resolve to the same flexible locale identifier, the scanner reports a conflict and loads the first
spelling in deterministic path order. Strict mode treats those spellings as separate locales.

`localeAliases` maps application-specific locale keys to identifiers known by Symfony Intl for validation. An alias
does not redirect translation lookup: files and calls continue to use the application locale key. Alias keys follow the
same flexible or strict policy as other identifiers. Targets must be known directly to Symfony Intl; aliases do not
chain, and two keys may not resolve to the same flexible locale identifier.

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

```text
Locale has no available translation strings: invalid_locale
🪪 lostInTranslation.invalidLocale.noTranslations

Missing translation string "foobar" for locales: invalid_locale
🪪 lostInTranslation.missingTranslationString

Unknown locale: invalid_locale
🪪 lostInTranslation.invalidLocale.unknown
```

## Invalid character encoding

`invalidCharacterEncodings` is enabled by default. It reports translation keys and values that are not valid UTF-8,
whether they come from a call or a translation file.

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

```text
Invalid character encoding for key "messages.\xf0(\x8c\xbc"
🪪 lostInTranslation.invalidCharacterEncoding

Invalid character encoding for value "\xf0(\x8c\xbc" in locale "ja"
🪪 lostInTranslation.invalidCharacterEncoding
```

## Translation-file errors

`translationLoaderErrors` is enabled by default. It reports parse failures, unsupported values, locale collisions, and
other errors encountered while loading translation files. Translation values must be strings or nested arrays that
ultimately contain strings.

Non-empty PHP array parents are valid translation lookups, matching Laravel's `Translator::get()` behavior. Using an
array parent counts its returned string leaves as used. When a group contains both a literal dotted item and the same
nested path, the literal item takes precedence as it does in Laravel's `Arr::get()`. A root JSON key also takes
precedence over an identically named grouped PHP item for an exact lookup; retrieving the whole PHP group still analyzes
the group's returned string leaves.

For example, numeric values in either format are reported at their source files:

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

```text
Invalid value: 2
🪪 lostInTranslation.translationLoaderError

Invalid value: 3
🪪 lostInTranslation.translationLoaderError
```

## Supported layouts

The scanner supports these layouts inside `langPath`:

- `<locale>.json`
- `<locale>/<group>.php`
- `vendor/<namespace>/<locale>/<group>.php`

Other nested PHP and JSON layouts are ignored. Vendor JSON catalogues and PHP files nested below the group level are
not part of Laravel's vendor override layout and remain unsupported.
