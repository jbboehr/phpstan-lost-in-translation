# Replacements and plural choices

## Replacements

`invalidReplacements` is enabled by default. It reports replacement names absent from the selected translation value
and names that match multiple casing variants such as `:foo` and `:FOO`.

<!-- akashi-example: invalid-replacements -->

```php
<?php

/* has a replacement that doesn't exist in the translation key */
__('exists in all locales', ['foo' => 'bar', 'bar' => 'bat'], 'en');

/* has multiple replacement variants */
__(':foo :FOO', ['foo' => 'bar'], 'en');
```

```text
Unused translation replacement: "foo"
🪪 lostInTranslation.invalidReplacement.unused

Replacement string matches multiple variants: "foo"
🪪 lostInTranslation.invalidReplacement.multipleVariants
```

## Choice syntax and coverage

`invalidChoices` and `requireCompleteChoiceCoverage` are enabled by default. They validate Laravel choice conditions and
report explicit conditions that do not cover every value PHPStan can infer for the count. Setting
`requireCompleteChoiceCoverage: false` suppresses only completeness warnings; malformed conditions and invalid bounds
remain errors while `invalidChoices` is enabled.

Fractional exact values and ranges use Laravel's inclusive numeric comparisons. Constant float counts are checked
precisely, and fractional ranges are projected onto inferred integer domains by rounding the lower bound up and the
upper bound down. For non-constant float counts, overlapping inclusive ranges are merged across the real-number domain;
integer-adjacent ranges such as `[*,0]` and `[1,*]` still leave a fractional gap.

Coverage uses only count members that PHPStan can prove Laravel supports. Unsupported members do not produce a
cascading `missingCase`; PHPStan's configured argument rules remain responsible for invalid arguments. Supported
members of a union are still checked.

<!-- akashi-example: invalid-choice -->

```php
<?php

trans_choice('{0} There are none|{1} There is one|[2] There are :count', 3, [], 'en');
```

```text
Explicit translation choice conditions do not cover all possible cases for number of type: 3
🪪 lostInTranslation.invalidChoice.missingCase
```

## Complete plural forms

`requireCompletePluralForms` is an opt-in translation-quality check. It reports an unconditioned choice when it provides
fewer positional forms than the locale can select.

The check uses the configured `localeAliases` target for plural policy while retaining the application's locale in the
diagnostic. For a full-sentence key with no separate translation value, it checks the source string itself. Missing
grouped keys remain the responsibility of missing-translation diagnostics because their keys are not plural content.

Locale spelling is matched exactly as Laravel matches it. An unaliased locale absent from Laravel's selector table uses
the first form only. Laravel's valid first-form fallback remains accepted while the option is disabled. The diagnostic
identifier is `lostInTranslation.invalidChoice.missingPluralForm`.

```neon
parameters:
    lostInTranslation:
        requireCompletePluralForms: true
```
