{{#title Lost in Translation - Laravel translation analysis for PHPStan}}

![Lost in Translation Utsusemi](images/lost-in-translation-banner.png)

# Lost in Translation

Lost in Translation is a PHPStan extension for statically checking Laravel translations. It finds missing and possibly
unused translation keys, validates replacements, plural choices, locales, encodings, and translation files, and can
inspect calls in Blade templates through Bladestan.

The extension is designed for gradual adoption. Its defaults enable higher-confidence diagnostics; checks that need a
project-wide dynamic-key or catalogue-usage policy remain opt-in.

Start with [Getting started](getting-started.md), then use the remaining chapters as task-oriented references:

- [Translation keys](translation-keys.md) covers missing, unused, dynamic, base-locale, and fuzzy-key diagnostics.
- [Blade templates](blade.md) explains the Bladestan integration and its compatibility bridges.
- [Replacements and plural choices](replacements-and-choices.md) defines validation of translator arguments.
- [Locales and translation files](locales-and-files.md) covers locale policy, supported layouts, and loader errors.
- [Configuration reference](configuration.md) lists every supported option and its default.
