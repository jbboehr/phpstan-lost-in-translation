# Translation keys

## Missing translations

Calls with statically inferable keys are checked against the application's translation catalogue.
`missingTranslationStrings` is enabled by default.

Fuzzy suggestions are also enabled by default. When a missing key resembles a known key, the diagnostic includes a
`Did you mean ...?` tip. Set `fuzzySearch: false` if suggestion generation is too expensive for a large catalogue.

```php
<?php

__('missing translation string');
```

```text
Missing translation string "missing translation string" for locales: ja
🪪 lostInTranslation.missingTranslationString
```

## Missing base-locale translations

Laravel treats an untranslated full sentence as its own value, so ordinary missing-key analysis does not report it as
missing from the base locale. Lost in Translation separately reports likely grouped keys that are absent from the base
locale. `missingTranslationStringsInBaseLocale` is enabled by default.

The check classifies a string as a grouped key when its group and every dot-separated key segment match
`[\w][\w\d]*(?:[_-][\w][\w\d]*)*`. Examples include `group-name.translation-key`,
`validation.custom.email.required`, and `package::group.nested.translation-key`. Full-sentence source strings are left
alone. Calls without an explicit locale include the configured base locale even when it has no translation file.

<!-- akashi: example=missing-base-translation -->

```php
<?php

__('foo.bar');
```

```text
Likely missing translation string "foo.bar" for base locale: en
🪪 lostInTranslation.missingBaseLocaleTranslationString
```

## Unused translations

`unusedTranslationStrings` compares all statically discovered uses with the complete translation catalogue. It is
disabled by default because dynamic construction and application-specific lookup mechanisms can make a used key appear
unused. The comparison runs only when the analyzed file set matches PHPStan's configured project paths, so CLI file and
subdirectory subsets do not produce catalogue-wide unused diagnostics.

```neon
parameters:
    lostInTranslation:
        unusedTranslationStrings: true
```

With Bladestan enabled, constant translation calls in reachable Blade templates count as uses. The extension preserves
those calls across Bladestan's nested analysis through a process-local compatibility bridge.

For example, a catalogue entry with no statically discovered use produces a diagnostic on the translation file:

```json
{
    "this string is not used anywhere": "this string is not used anywhere"
}
```

```text
Possibly unused translation string "this string is not used anywhere" for locale: ja
🪪 lostInTranslation.possiblyUnusedTranslationString
```

## Dynamic translations

`disallowDynamicTranslationStrings` reports calls whose key type PHPStan cannot reduce to a finite set of strings. It is
disabled by default.

```neon
parameters:
    lostInTranslation:
        disallowDynamicTranslationStrings: true
```

<!-- akashi: example=dynamic-translation -->

```php
<?php

/** @var \Illuminate\Contracts\Translation\Translator $translator */
/** @var string $dynamic */
$translator->get($dynamic);

/** @var "foo"|"bar"|\Exception $craycray */
$translator->get($craycray);
```

```text
Disallowed dynamic translation string of type: string
🪪 lostInTranslation.dynamicTranslationString

Disallowed dynamic translation string of type: 'bar'|'foo'|Exception
🪪 lostInTranslation.dynamicTranslationString
```
