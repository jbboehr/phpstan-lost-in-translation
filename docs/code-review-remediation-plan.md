# Code Review Remediation Plan

Reviewed against commit `692b6eb4d4d585b2f8447896e60be93a9acda34a` on 2026-08-09.

## Executive summary

The initial full-codebase review found ten reproducible issues:

- 2 high-priority filesystem and startup failures.
- 6 medium-priority analysis, lookup, and packaging correctness issues.
- 2 low-priority diagnostic and optional implementation defects.

The existing automated checks are green, including Composer validation, PHPCS,
PHPStan, 52 PHPUnit tests, the end-to-end suite, and Nix evaluation for all
declared systems. The findings are primarily uncovered edge cases rather than
failures in currently tested paths.

The main risk areas are:

1. Translation-directory discovery can terminate analysis or load files that
   do not match a supported layout.
2. Valid PHP and Laravel call forms can be silently interpreted incorrectly.
3. Locale and key normalization are inconsistent across validation, caching,
   and lookup.

A follow-up review found an uncovered script-subtag case in CR-05 and three
small loader-maintenance defects. The complete follow-up triage is recorded
below so the separate review report is not required to track outstanding work.

## Findings summary

| ID | Priority | Area | Summary |
| --- | --- | --- | --- |
| CR-01 | P1 | Translation loading | A missing language directory throws during container construction. |
| CR-02 | P1 | Translation loading | Files whose relative paths do not match the expected pattern are still processed. |
| CR-03 | P2 | Call parsing | Named arguments are assigned by position rather than parameter name. |
| CR-04 | P2 | Key parsing | Namespaced keys are cached under their normalized plain key. |
| CR-05 | P2 | Locale handling | Flexible locale handling diverges between validation and lookup and mishandles script subtags. |
| CR-06 | P2 | Base locale | The base locale is not analyzed when it has no translation files. |
| CR-07 | P2 | Packaging | Shipped classes use undeclared or development-only runtime dependencies. |
| CR-08 | P2 | Replacements | Equivalent placeholder variants are counted more than once. |
| CR-09 | P3 | Diagnostics | Invalid-value encoding errors display the translation key. |
| CR-10 | P3 | Fuzzy search | Searching an empty `MyFuzzyStringSet` emits an undefined-key warning. |
| CR-11 | P3 | Translation loading | JSON line-number parsing leaves its file handle open. |
| CR-12 | P3 | Types | `PhpLoader::load()` declares `mixed` although it always returns `LoadResult`. |
| CR-13 | P3 | Locations | Translation locations store `getRealPath()` without guarding against `false`. |

## Follow-up repository-review triage

| Review ID | Disposition |
| --- | --- |
| RV-01 | Tracked as the script-subtag follow-up to CR-05; implementation and regression coverage complete. |
| RV-02 | Tracked by CR-08; implementation and regression coverage complete. |
| RV-03 | Tracked by CR-09; implementation and regression coverage complete. |
| RV-04 | Source-string fallback for a fileless base locale is intentional and covered in both replacement and choice rule tests. |
| RV-05 | Tracked by CR-07; complete. |
| RV-06 | The deterministic first-spelling-wins policy is intentional, and the existing diagnostic says that all files for the losing spelling are ignored. |
| RV-07 | Tracked by CR-02 as a product decision; supported layouts and the vendor-override limitation are documented in the README. |
| RV-08 | Tracked by CR-10; open. |
| RV-09 | Load-time and call-time encoding checks cover different diagnostic paths; the concrete CR-09 message defect is fixed. |
| RV-10 | The compiled Blade diagnostic bridge and regression coverage are complete. |
| RV-11 | Tracked by CR-11; open. |
| RV-12 | Deferred while PHP-Parser 4 and 5 compatibility is required; the compatibility alias is deliberate. |
| RV-13 | Tracked by CR-12; open. |
| RV-14 | Tracked by CR-13; open. |

## Detailed findings and remediation

### CR-01: Missing language directories crash analysis

**Status:** Implementation complete. Additional relative-path and symlink
regression coverage remains open.

**Location:** `src/TranslationLoader/TranslationLoader.php:82`,
`src/TranslationLoader/TranslationLoader.php:270`

**Current behavior:**

When a configured language path does not exist, `realpath()` returns `false`
and the constructor silently falls back to the detected default path. The
fallback is passed directly to `Finder::in()`. If it also does not exist,
Symfony Finder throws `DirectoryNotFoundException` while PHPStan is building
its container.

This was reproduced by constructing a loader with a nonexistent absolute path;
the resulting exception referred to the fallback `lang` directory rather than
the configured path.

**Impact:**

- PHPStan cannot start for applications without a local language directory.
- A misspelled configured path is discarded, obscuring the configuration
  problem.

**Proposed remediation:**

1. Preserve an explicitly configured path instead of replacing it after a
   failed `realpath()` call.
2. Decide and document separate behavior for:
   - an absent auto-detected default directory, which can safely represent an
     empty translation set; and
   - an explicitly configured missing directory, which should produce a clear
     configuration error.
3. Do not call `Finder::in()` until the resolved path has been validated.

**Regression tests:**

- No configured path and no default `lang` directory.
- Explicit nonexistent relative and absolute paths.
- Existing relative, absolute, and symlinked paths.

### CR-02: Nonmatching translation paths are processed

**Status:** Remediation steps 1 and 2 are implemented. The vendor-override
decision and its regression coverage remain open.

**Location:** `src/TranslationLoader/TranslationLoader.php:293`

**Current behavior:**

The scan loop skips a file only when `preg_match()` returns `false`. A regular
nonmatch returns `0`, so the code continues, reads an undefined `$matches[1]`,
and attempts to load the file under an empty locale. Finder scans recursively,
making nested PHP and JSON files reachable through this path.

This was reproduced with a language path containing unsupported nested files:
the loader emitted an undefined-array-key warning and attempted to execute an
unrelated PHP file.

**Impact:**

- Analysis can terminate while loading an unrelated or nested file.
- Standard nested layouts, including vendor translation overrides, are unsafe.
- Files can be associated with an empty locale.

**Proposed remediation:**

1. Require `preg_match(...) === 1` before reading captures.
2. Initially skip unsupported paths deterministically.
3. Separately decide whether Laravel vendor translation overrides should be
   supported and, if so, parse their namespace, locale, and group explicitly.

**Regression tests:**

- Root JSON locale files and one-level locale PHP files continue to load.
- Unrelated nested PHP and JSON files are ignored without warnings.
- Add fixtures for the chosen vendor override behavior.

### CR-03: Named arguments are parsed positionally

**Status:** Implementation and regression coverage complete.

**Location:** `src/LostInTranslationHelper.php:155`

**Current behavior:**

Translation arguments are assigned from `count($args)` and numeric offsets.
`PhpParser\Node\Arg::$name` is ignored. Calls that omit optional parameters or
reorder named parameters therefore assign values to the wrong semantic slots.

For example, this call produced no locale diagnostics:

```php
__('exists in all locales', locale: 'invalid_locale');
```

The equivalent positional call correctly produced the invalid-locale and
missing-translation diagnostics.

**Impact:**

- False negatives for invalid and missing locales.
- Replacement and choice analysis can inspect the wrong argument type.
- Unused-translation collection can record an incorrect locale.

**Proposed remediation:**

1. Define the supported parameter names for `__`, `trans`, `trans_choice`,
   translator methods, and facade methods.
2. Bind named arguments first and apply positional assignment only to unnamed
   arguments.
3. Handle mixed positional and named arguments according to PHP semantics.

**Regression tests:**

- Named `locale` with omitted `replace`.
- Named `replace`, `number`, and `key` arguments.
- Reordered named arguments.
- Mixed positional and named calls for functions, translator methods, and the
  `Lang` facade.

### CR-04: Namespaced keys pollute the plain-key cache

**Status:** Implementation and regression coverage complete.

**Location:** `src/TranslationLoader/TranslationLoader.php:264`

**Current behavior:**

`parseKey()` overwrites `$key` with its normalized group/item value and then
uses that normalized value as the cache key. Parsing
`vendor::messages.foo` stores the vendor result under `messages.foo`. A later
plain `messages.foo` lookup returns the vendor namespace.

**Impact:**

- Translation lookup depends on call order.
- Plain application keys can be reported against the wrong namespace.
- Missing and unused translation results can be incorrect.

**Proposed remediation:**

Preserve the original input in a separate variable and cache the parsed result
under that original string. The normalized key should remain part of the
cached value only.

**Regression tests:**

- Parse a namespaced key followed by the equivalent plain key.
- Repeat the test in reverse order.
- Verify separate cache entries and lookup results for both forms.

### CR-05: Flexible locales validate but do not resolve

**Status:** Implementation and regression coverage complete. Flexible mode
uses canonical locale keys throughout validation, discovery, lookup,
base-locale comparison, and unused-translation matching. Canonicalization is
subtag-aware: language subtags are lowercase, four-letter script subtags are
title case, and region/other subtags retain the existing uppercase policy.
When discovered spellings collide, the first spelling in deterministic path
order is retained and each conflicting alias produces one loader diagnostic;
strict mode keeps the spellings distinct.

**Location:** `src/Utils.php:62`,
`src/TranslationLoader/TranslationLoader.php:121`,
`src/TranslationLoader/TranslationLoader.php:142`

**Previous behavior:**

With `strictLocales: false`, locale validation normalizes case and replaces
dashes with underscores. `TranslationLoader::hasLocale()` and `get()` still use
the original locale string as an exact array key.

For example, `JA` passes flexible locale validation, but a call using it emits
"no available translation strings" and "missing translation" even when the
`ja` locale contains the requested key.

The initial remediation uppercased everything following the language subtag.
That made regional identifiers such as `pt-BR` work, but rewrote valid script
identifiers such as `zh_Hans` and `sr_Latn_RS` to the invalid `zh_HANS` and
`sr_LATN_RS`. Flexible mode consequently rejected identifiers that strict mode
accepted.

**Impact:**

- Documented flexible locale forms cause false positives.
- Base-locale comparisons can also fail because they are exact.

**Implemented remediation:**

1. Introduce one locale canonicalization function used by validation,
   discovery, base-locale comparison, `hasLocale()`, and `get()`.
2. Wire `strictLocales` into the lookup layer, or build a canonical alias map
   during scanning.
3. Detect and define behavior for two discovered locale directories that
   canonicalize to the same identifier.
4. Canonicalize each subtag by role instead of uppercasing the complete suffix.

**Regression tests:**

- Case variants such as `JA` and `ja`.
- Dash/underscore variants for a regional locale.
- Script-only and script-plus-region identifiers such as `zh_Hans` and
  `sr_Latn_RS`.
- Strict mode continues to require an exact identifier.
- Canonicalization collisions produce deterministic diagnostics.

### CR-06: A fileless base locale is excluded

**Status:** Implementation and regression coverage complete. Implicit locale
selection now includes the configured base locale, deduplicates a discovered
base locale using the configured strict/flexible policy, and remains stably
sorted. Calls with an explicit locale remain limited to their explicit values.

**Location:** `LostInTranslationHelper::gatherPossibleTranslations()`,
`TranslationLoader::getLocalesForImplicitLookup()`

**Previous behavior:**

Calls without an explicit locale are checked only against
`TranslationLoader::getFoundLocales()`. The configured base locale is absent
from that list when it has no translation file, so
`MissingTranslationStringInBaseLocaleRule` never sees it.

This was reproduced with `fr` as the base locale and translation files only for
`en`, `ja`, and `zh`; the gathered translation candidates excluded `fr`.

**Impact:**

- Grouped keys missing from the base locale are silently missed.
- Behavior conflicts with `hasLocale()`, which otherwise treats the configured
  base locale as valid even without data.

**Proposed remediation:**

Add the configured base locale to the implicit lookup set, then deduplicate and
stably sort the locale list. Explicit locale arguments should remain scoped to
the explicit values.

**Regression tests:**

- Base locale with and without corresponding files.
- Base locale already present in discovered locales is not duplicated.
- Explicit locale calls do not gain unrelated locales.

### CR-07: Runtime dependencies are incomplete

**Status:** Implementation and regression coverage complete. The JSON error
formatter now uses native throwing JSON encoding, binary escaping uses an
ASCII byte-range check instead of `ext-ctype`, and the unusably slow Fuse
implementation and `loilo/fuse` development dependency have been removed. CI
now installs runtime dependencies without development packages and exercises
the registered extension and custom formatter.

**Location:** `composer.json`, `JsonErrorFormatter::formatErrors()`,
`Utils::escapeBinary()`, `.github/workflows/ci.yml`

**Previous behavior:**

- The registered JSON error formatter uses `nette/utils`, but the package does
  not require it.
- `FuseFuzzyStringSet` is shipped under `src/`, while `loilo/fuse` is listed
  only under `require-dev`.
- `Utils::escapeBinary()` calls `ctype_print()`, but `ext-ctype` is not declared.

A dry-run production installation removes both `nette/utils` and `loilo/fuse`.
PHPStan's PHAR does not expose the unprefixed `Nette\Utils\Json` class. Selecting
the formatter in a minimal consumer installation can therefore fail at
runtime.

**Impact:**

- The custom JSON formatter is not self-contained.
- A shipped fuzzy-set implementation cannot be constructed in a production
  installation.
- Invalid-binary diagnostics depend on an undeclared extension.

**Implemented remediation:**

1. Replaced `nette/utils` and `ext-ctype` usage with native PHP behavior.
2. Removed the Fuse implementation, benchmark, and `loilo/fuse` dependency.
3. Added a `composer install --no-dev` smoke test that builds the extension
   container, produces a real extension diagnostic, validates the formatter's
   JSON document, and verifies the diagnostic is present.

### CR-08: Equivalent replacement variants are double-counted

**Status:** Implementation and regression coverage complete. Placeholder
spellings are deduplicated before the rule counts which variants occur in the
translation value.

**Location:** `src/CallRule/InvalidReplacementRule.php:74`

**Current behavior:**

The rule independently checks ucfirst, uppercase, and original placeholders.
For keys such as `FOO`, these transformations are identical, so one `:FOO`
placeholder is counted three times and reported as matching multiple variants.

**Impact:**

- Valid replacement arrays generate false-positive diagnostics.
- Mixed-case keys such as `Foo` can have the same problem.

**Implemented remediation:**

Generate the three placeholder strings, deduplicate them, and then count the
distinct variants present in the translation value.

**Regression tests:**

- Lowercase, title-case, and uppercase replacement keys.
- One valid placeholder versus genuinely distinct variants in the same value.
- Multibyte replacement keys.

### CR-09: Invalid-value diagnostics display the key

**Status:** Implementation and regression coverage complete. Invalid-value
diagnostics now display the malformed translation value while retaining the
original key, locale, and value in metadata.

**Location:** `src/CallRule/InvalidCharacterEncodingRule.php:51`

**Previous behavior:**

When the translation value contains invalid encoding, the diagnostic formats
`$key` instead of `$value`. The metadata contains the correct value, but the
human-readable message does not.

**Implemented remediation:**

Pass `$value` to `Utils::e()`. Regression coverage uses a valid full-sentence
translation key and a distinct malformed value, ensuring sentence keys remain
unchanged and cannot be substituted into the value diagnostic.

### CR-10: Empty custom fuzzy sets emit a warning

**Location:** `src/Fuzzy/MyFuzzyStringSet.php:130`

**Current behavior:**

After sorting the candidate deltas, the implementation indexes the result of
`array_key_first()` without checking whether the array is empty. Searching a
new empty set emits an undefined-array-key warning before returning `null`.

**Proposed remediation:**

Return `null` immediately when the candidate array is empty. Add result-based
tests for all fuzzy-set implementations; the current benchmark-backed tests do
not assert search results.

### CR-11: JSON line-number parsing leaks a file handle

**Location:** `src/TranslationLoader/JsonLoader.php:102`

**Current behavior:**

`JsonLoader::buildLineNumberMap()` opens a translation file for streaming but
does not close the handle after parsing or when parsing throws.

**Proposed remediation:**

Close the handle in a `finally` block and retain the existing error conversion
in `load()`. Add coverage for successful and failing streaming parses.

### CR-12: `PhpLoader::load()` has an unnecessarily broad return type

**Location:** `src/TranslationLoader/PhpLoader.php:43`

**Current behavior:**

The method declares `mixed`, although every return path constructs a
`LoadResult` and the PHPDoc already promises that type.

**Proposed remediation:**

Declare `LoadResult` as the native return type and remove the redundant return
PHPDoc.

### CR-13: Translation locations can retain `false` as a file path

**Location:** `src/TranslationLoader/TranslationLoader.php:443`

**Current behavior:**

`SplFileInfo::getRealPath()` can return `false`, but its result is stored in a
location shape that promises a string and is later passed to diagnostics.

**Proposed remediation:**

Fall back to `getPathname()` when a real path is unavailable and cover the
location behavior with a focused loader test where practical.

## Additional maintenance observations

### PHPUnit configuration schema

**Status:** Migrated while adding mutation testing. The shared configuration
now avoids version-specific coverage elements, and CI supplies coverage filters
and report destinations through PHPUnit's command-line interface.

The project supports PHPUnit 9 through 11, whose XML coverage schemas are not
mutually compatible. Keep version-specific coverage settings out of the shared
configuration and verify future changes across the full matrix. The migration
also removes `beStrictAboutTodoAnnotatedTests`: PHPUnit 11 no longer accepts the
setting, so PHPUnit 9 and 10 no longer treat `@todo` annotations as risky through
the shared configuration.

### Development dependency advisories

**Status:** CI now audits locked runtime dependencies separately. Compatibility
matrix updates disable Composer policy blocking only while resolving the
intentionally retained Laravel 9 through 11 development fixtures.

`composer audit --locked` reports three advisories against the locked Laravel
10 development baseline. Laravel 10 is retained to exercise PHP 8.1
compatibility, and `composer audit --locked --no-dev` is clean.

Ongoing policy:

- Document or configure explicit exceptions for unavoidable development-only
  compatibility fixtures.
- Continue testing supported maintained Laravel versions separately from the
  PHP 8.1 compatibility baseline.

### Property-based testing with Eris

Evaluate a targeted Eris suite after CR-05 introduces shared locale
canonicalization. Useful properties include normalization idempotence,
case/dash/underscore equivalence in flexible mode, and exact preservation in
strict mode. Generated plain and namespaced keys could also exercise cache and
lookup order independence.

Keep property tests supplementary to focused examples and use a fixed default
seed with an override for exploration and replay. Current Eris releases support
PHP 8.1 but list PHPUnit 10 and newer; validate that constraint against this
project's declared PHPUnit 9 compatibility before adding it to the main test
suite. An isolated property-test job is preferable if PHPUnit 9 remains
supported.

### Locale-aware plural completeness

**Status:** Deferred as a separate product decision after the BookStack
integration review.

Laravel accepts one or more unconditioned choice segments. It selects a
segment using the locale's plural index and legally falls back to the first
segment when that index is absent. `InvalidChoiceRule` therefore accepts
single-form locales and locale-specific three-or-more-form translations rather
than reporting them as malformed. When conditioned and unconditioned segments
are mixed, the unconditioned plural path is treated as a fallback and suppresses
explicit-condition `missingCase` diagnostics.

This runtime-compatible syntax policy cannot distinguish an intentional single
form from a likely missing delimiter, such as the Icelandic BookStack string
recorded in `docs/development/bookstack-integration-report.md`. If stronger
translation-quality validation is desired, design it as a separate diagnostic
with an explicit policy for locale aliases, possible number types, Laravel's
first-segment fallback, and applications that intentionally provide fewer
plural forms. Do not fold that policy back into `invalidChoice.malformed`.

### Compiled Blade diagnostic paths

**Status:** A compatibility bridge and regression coverage are implemented.

Bladestan runs compiled templates in a nested PHPStan analysis. Version 0.6
drops nested identifiers, while version 0.11.7 preserves identifiers but
replaces translation metadata and drops tips. Both versions embed stable
`file` and `line` markers in compiled PHP.

The extension now queues its structured diagnostics inside the nested analysis,
drains them through an outer collector, and rebuilds them after collection. The
rebuilt error retains the original identifier, translation metadata, and tip,
adds Bladestan's template path and line metadata, and keeps the outer view-call
location for compatibility with Bladestan's formatter. The bridge is enabled by
default and can be disabled with `bridgeBladeDiagnostics: false` if a future
Bladestan release conflicts with it.

Unit coverage asserts queue sharing, marker resolution, metadata, tips, and
reconstruction. End-to-end coverage verifies a real Blade replacement error on
the locked Bladestan 0.6 dependency; an isolated PHP 8.4/Laravel 12 run also
verified the same behavior against Bladestan 0.11.7.

### PHP-Parser compatibility aliases

`KeyLineNumberVisitor` uses the deprecated `Node\Expr\ArrayItem` alias and
`jsonSerialize()` to work across PHP-Parser 4 and 5. Keep this compatibility
path while PHPStan 1 remains supported, then migrate to version-specific typed
nodes when the supported dependency range permits it.

## Proposed implementation order

### Phase 1: Make translation discovery safe

1. CR-01: missing directory handling.
2. CR-02: exact path-match validation and nested-layout decision.

These changes should land first because they prevent PHPStan from starting or
can execute unintended files during container construction.

### Phase 2: Restore analysis correctness

1. CR-03: named argument binding.
2. CR-04: key-cache isolation.
3. CR-05: shared locale canonicalization.
4. CR-06: base-locale inclusion.

Locale canonicalization should be designed before modifying lookup call sites
so validation and storage cannot diverge again.

### Phase 3: Fix packaging and diagnostics

1. CR-07: runtime dependency declarations and production-install smoke test.
2. CR-08: placeholder variant deduplication.
3. CR-09: invalid-value message correction.

### Phase 4: Harden optional implementations and scaffolding

1. CR-10: empty fuzzy-set handling and result assertions.
2. CR-11: close JSON line-map file handles.
3. CR-12: tighten the PHP loader return type.
4. CR-13: guard translation location paths.
5. Migrate the PHPUnit configuration with matrix verification.
6. Add runtime-only Composer auditing to CI.
7. Triage the advisory Infection baseline and establish a covered-code MSI
   threshold.
8. Evaluate targeted Eris properties after locale canonicalization is stable.

## Definition of done

- Every CR item has a focused regression test that fails before its fix.
- The full PHP and Laravel compatibility matrix remains green.
- `composer install --no-dev` can build the registered extension services and
  exercise the custom formatter with a real extension diagnostic.
- Composer validation, PHPCS, PHPStan, PHPUnit, end-to-end tests, benchmarks,
  actionlint, and `nix flake check -L` pass.
- No new PHP warnings, deprecations, or ignored static-analysis errors are
  introduced.
- User-visible behavior changes are reflected in `README.md` where applicable.
