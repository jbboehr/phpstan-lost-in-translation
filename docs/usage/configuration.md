# Configuration reference

All options live under `parameters.lostInTranslation`. The block below contains every supported key and its default;
the documentation test verifies it against both the extension schema and runtime defaults.

<!-- configuration-reference:start -->
```neon
parameters:
    lostInTranslation:
        # preserve identifiers, metadata, tips, and template locations across Bladestan's nested analysis
        bridgeBladeDiagnostics: true
        # report translation keys whose values PHPStan cannot infer statically
        disallowDynamicTranslationStrings: false
        # strings in the base locale are not missing unless they look like grouped keys; may use Laravel's value
        baseLocale: null
        # path to the language directory when it is not ./lang; may use Laravel's value
        langPath: null
        # validate application-specific locale keys through known Symfony Intl locales without changing lookup keys
        localeAliases: []
        # report invalid character encodings
        invalidCharacterEncodings: true
        # report malformed choice conditions and invalid bounds
        invalidChoices: true
        # require explicit choice conditions to cover every possible value of the inferred number type
        requireCompleteChoiceCoverage: true
        # require unconditioned choices to provide every positional plural form the locale can select
        requireCompletePluralForms: false
        # report invalid locale identifiers in calls and translation paths, or locales with no translation strings
        invalidLocales: true
        # analyze translation replacements for invalid values
        invalidReplacements: true
        # attach similar known-key suggestions to missing-translation diagnostics
        fuzzySearch: true
        # report missing translation strings
        missingTranslationStrings: true
        # report base-locale strings that look like grouped keys but are missing a translation
        missingTranslationStringsInBaseLocale: true
        # require locale identifiers to match exactly
        strictLocales: false
        # report parse failures, invalid values, locale conflicts, and other non-locale loader diagnostics
        translationLoaderErrors: true
        # compare statically used translations with the complete catalogue
        unusedTranslationStrings: false
```
<!-- configuration-reference:end -->

See the task-oriented chapters for the behavior and adoption trade-offs behind these options.

## Integration contract

The supported integration surface consists of the `lostInTranslation` configuration schema, diagnostic identifiers and
metadata, and registered error-format names and output. The diagnostic and metadata-key constants on
`jbboehr\PHPStanLostInTranslation\Identifier` are the package's supported PHP API. Other PHP types are marked
`@internal`; their constructors, methods, and serialized forms are process plumbing and may change between releases.
For example, PHP integrations can refer to the missing-key diagnostic as `Identifier::MISSING_TRANSLATION_STRING`
instead of repeating its string value.

The `lostInTranslationJson` error format remains available for tools that consume missing-key output:

```console
vendor/bin/phpstan analyse --error-format=lostInTranslationJson
```

Its missing-translation sections are grouped by diagnostic identifier and locale, with translation keys mapped to
`null` so each locale section resembles a Laravel JSON translation file. Other diagnostic sections remain lists of
affected keys. This format is separate from PHPStan's built-in `json` error format.
