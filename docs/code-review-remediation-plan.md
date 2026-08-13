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
| RV-07 | Complete. Laravel vendor override discovery, namespaced lookup, diagnostics, metadata, and documentation are covered. |
| RV-08 | Tracked by CR-10; implementation and regression coverage complete. |
| RV-09 | Load-time and call-time encoding checks cover different diagnostic paths; the concrete CR-09 message defect is fixed. |
| RV-10 | The compiled Blade diagnostic bridge and regression coverage are complete. |
| RV-11 | Tracked by CR-11; implementation and regression coverage complete. |
| RV-12 | Deferred while PHP-Parser 4 and 5 compatibility is required; the compatibility alias is deliberate. |
| RV-13 | Tracked by CR-12; implementation and regression coverage complete. |
| RV-14 | Tracked by CR-13; implementation and regression coverage complete. |

## Detailed findings and remediation

### CR-01: Missing language directories crash analysis

**Status:** Implementation and regression coverage complete. Explicit missing
paths produce configuration errors, absent auto-detected paths produce an empty
translation set, and existing absolute, relative, and symlinked paths load
normally.

**Location:** `src/TranslationLoader/TranslationLoader.php:82`,
`src/TranslationLoader/TranslationLoader.php:270`

**Previous behavior:**

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

**Implemented remediation:**

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

**Status:** Implementation and regression coverage complete. Root JSON,
one-level locale PHP, and Laravel vendor override layouts are matched exactly;
other nested files are ignored.

**Location:** `src/TranslationLoader/TranslationLoader.php:293`

**Previous behavior:**

The scan loop skips a file only when `preg_match()` returns `false`. A regular
nonmatch returns `0`, so the code continues, reads an undefined `$matches[1]`,
and attempts to load the file under an empty locale. Finder scans recursively,
making nested PHP and JSON files reachable through this path.

This was reproduced with a language path containing unsupported nested files:
the loader emitted an undefined-array-key warning and attempted to execute an
unrelated PHP file.

**Impact:**

- Analysis can terminate while loading an unrelated or nested file.
- Nested layouts were unsafe until each supported shape received an exact match.
- Files can be associated with an empty locale.

**Implemented remediation:**

1. Require `preg_match(...) === 1` before reading captures.
2. Initially skip unsupported paths deterministically.
3. Add an exact `vendor/<namespace>/<locale>/<group>.php` match and preserve
   the namespace through lookup, fuzzy candidates, diagnostics, and metadata.

**Regression tests:**

- Root JSON locale files, one-level locale PHP files, and Laravel vendor
  override files load under distinct key spaces.
- Unrelated nested PHP and JSON files are ignored without warnings.
- Vendor JSON and unsupported deeper vendor paths remain ignored.

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

**Status:** Implementation and regression coverage complete. Empty sets and
searches with no viable candidates now return `null` without accessing a
missing array offset.

**Location:** `src/Fuzzy/MyFuzzyStringSet.php:130`

**Previous behavior:**

After sorting the candidate deltas, the implementation indexes the result of
`array_key_first()` without checking whether the array is empty. Searching a
new empty set emits an undefined-array-key warning before returning `null`.

**Implemented remediation:**

Return `null` immediately when the candidate array is empty and retain the
winning index after sorting instead of resolving it twice. Result-based tests
cover empty sets, close and distant searches for the custom and naive
implementations, null behavior, and the memoizing wrapper.

### CR-11: JSON line-number parsing leaks a file handle

**Status:** Implementation and regression coverage complete. Streaming handles
are closed after successful parses and when parsing throws.

**Location:** `src/TranslationLoader/JsonLoader.php:102`

**Previous behavior:**

`JsonLoader::buildLineNumberMap()` opens a translation file for streaming but
does not close the handle after parsing or when parsing throws.

**Implemented remediation:**

Close the handle in a `finally` block and retain the existing error conversion
in `load()`. A separate-process close interceptor proves that cleanup runs for
both successful and failing streaming parses.

### CR-12: `PhpLoader::load()` has an unnecessarily broad return type

**Status:** Implementation and regression coverage complete. The native return
type now matches every implementation path.

**Location:** `src/TranslationLoader/PhpLoader.php:43`

**Previous behavior:**

The method declares `mixed`, although every return path constructs a
`LoadResult` and the PHPDoc already promises that type.

**Implemented remediation:**

Declare `LoadResult` as the native return type and remove the redundant return
PHPDoc. Reflection-based coverage locks in the concrete, non-nullable type.

### CR-13: Translation locations can retain `false` as a file path

**Status:** Implementation and regression coverage complete. Unresolved real
paths now fall back to the original pathname.

**Location:** `src/TranslationLoader/TranslationLoader.php:443`

**Previous behavior:**

`SplFileInfo::getRealPath()` can return `false`, but its result is stored in a
location shape that promises a string and is later passed to diagnostics.

**Implemented remediation:**

Fall back to `getPathname()` when a real path is unavailable. The focused
loader test uses a temporary PHP translation file that removes itself during
loading, reproducing the unresolved-real-path case end to end.

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

### Mutation testing baseline

**Status:** Complete. The reviewed PHP 8.4 campaign generates 1,011 covered
mutants, kills 834, and records a covered-code MSI of 82.49%. CI now enforces
80-point overall and covered-code minimums. Focused tests cover the observable
Blade queue, loader metadata, choice and replacement edge cases, memoization,
formatter exit status, and utility boundaries added during triage.

The 177 survivors are classified by component in
`docs/mutation-testing.md`. Equivalent mutants are documented rather than
hidden with broad ignore rules; remaining behavioral variants provide a
prioritized input for future work tied to real diagnostics.

### Doctrine of the Second Sun

**Status:** The portable guidance is installed as the Composer development dependency
`jbboehr/doctrine-of-the-second-sun:dev-master`, with the selected revision pinned in `composer.lock`. Repository-local
scope, citation, workflow, Ruinenwert, technical-writing, and Code of Sovereignty policy is recorded in `AGENTS.md` and
linked from `CONTRIBUTING.md`.

Logion coverage is prospective for new named declarations under `src/`; no backfill was performed during adoption.
Ruinenwert is adopted for technical preservation and replacement boundaries, while its succession and stewardship
recommendations are excluded. Repository governance instead follows the explicitly adopted Code of Sovereignty.

The bundled enforcement adapter remains disabled because it supports PHPStan 2.x while this package supports PHPStan
1.12 and 2.x. Reconsider it through a dedicated PHPStan 2 verification job or after PHPStan 1 support is dropped; do not
include it in the shared analysis configuration before then.

### Property-based testing with Eris

**Status:** Complete. The property suite covers flexible locale normalization
idempotence, case and separator equivalence, strict spelling preservation, and
plain versus namespaced translation lookup independence across cache insertion
orders.

No supported Eris release spans this project's PHPUnit 9 through 11 matrix:
Eris 0.14 targets PHPUnit 8 and 9, while Eris 1.1 targets PHPUnit 10 and newer.
The suite therefore runs as a locked Composer project under `tools/eris/` with
Eris 1.1 and PHPUnit 10 on PHP 8.1. A dedicated CI job runs `composer eris`
without changing root dependency resolution or the package archive.

The tracked default seed is `20260811`; `ERIS_SEED` remains available for
exploration and replay. Focused example tests remain the primary regression
suite, with generated properties providing supplementary invariant coverage.

### Akashi-backed README example verification

**Status:** Complete for README translation-call diagnostics. Seven PHP examples are selected with invisible Akashi
markers and analyzed against a dedicated language fixture. The harness compares stable extension identifiers from an
external expectation map together with their code-relative lines, so public snippets contain no tool-only expectation
comments while diagnostic attribution remains covered.

The remaining README fences were classified but intentionally excluded from this initial corpus. Blade examples require
template compilation, translation-file examples describe loader inputs rather than executable calls, and NEON, JSON,
console output, installation commands, and type-inference fragments need different execution contracts. Internal plans
and historical reports remain outside the public documentation corpus.

The locked harness deliberately lives under `tools/akashi/` with Laravel 12, PHPUnit 11, and PHPStan 2, while existing
tests continue to cover PHPStan 1.12 and the PHP 8.1 floor. Akashi now supports PHP 8.1 and PHPStan 1.12, but keeping the
documentation stack isolated prevents it from changing the root compatibility resolution. The PHP 8.2 `documentation`
shell and dedicated CI job run `composer docs:check`; it is not part of the PHP 8.1 `composer check:full` gate. The first
run also corrected the invalid-locale console output to include the extension's simultaneous missing-translation
diagnostic.

### Locale-aware plural completeness

**Status:** Complete as an opt-in translation-quality diagnostic.

Laravel accepts one or more unconditioned choice segments. It selects a
segment using the locale's plural index and legally falls back to the first
segment when that index is absent. `InvalidChoiceRule` therefore accepts
single-form locales and locale-specific three-or-more-form translations rather
than reporting them as malformed. When conditioned and unconditioned segments
are mixed, the unconditioned plural path is treated as a fallback and suppresses
explicit-condition `missingCase` diagnostics.

`requireCompletePluralForms` is disabled by default so this runtime-compatible
syntax remains valid. When enabled, `invalidChoice.missingPluralForm` reports an
unconditioned translation with fewer positional forms than Laravel's selector
can choose for the locale. The check is intentionally translation-level rather
than narrowed to the number type at one call site: its purpose is to find a
translation that relies on Laravel's first-form fallback anywhere in the
locale's number domain.

Locale aliases select the plural policy while diagnostics retain the original
application locale. After resolving an alias, the rule exact-matches Laravel's
case-sensitive, underscore-sensitive selector table and treats unlisted locale
variants as one-form locales, matching Laravel's default. The rule counts every pipe-delimited segment because
Laravel strips explicit conditions from the complete segment list before its
positional fallback, but it runs only when at least one unconditioned segment
exists and suppresses the warning after malformed conditions. Explicit-only
choices remain governed by `requireCompleteChoiceCoverage`. The diagnostic is
separate from `invalidChoice.malformed`, and full-sentence keys are checked as
source values when no translated value exists.

### Explicit choice-condition coverage

**Status:** Complete after focused BookStack triage.

Explicit conditions continue to be checked against the complete PHPStan number
domain because finding translation strings with incomplete coverage is a core
feature. The rule now handles `non-negative-int` as `int<0, max>`, suppresses
secondary coverage findings when malformed conditions make the union
unreliable, and describes the diagnostic as an explicit-condition gap rather
than a Laravel runtime failure. `requireCompleteChoiceCoverage` is enabled by
default and can disable this check without disabling malformed-choice and
invalid-bound diagnostics. Numeric comma lists remain invalid Laravel range
syntax; contiguous lists receive a two-bound range suggestion.

### Application-specific locale aliases

**Status:** Complete after the BookStack integration follow-up.

`localeAliases` maps an application locale key to a Symfony Intl locale for
validation without redirecting translation discovery or lookup. Alias keys
respect `strictLocales`; flexible mode canonicalizes their spelling in the
same way as call and path locales. Focused tests cover loader diagnostics,
call-site validation, lookup isolation, invalid targets, and empty mappings.
Invalid targets, non-string or empty entries, and colliding canonical keys fail
as configuration errors; alias targets are not chained.
The pinned BookStack canary validates `de_informal` through `de_DE`, leaving
the application-only translation pass clean.

### Namespaced translation helper resolution

**Status:** Complete for item 1 of GitHub issue #6.

`LostInTranslationHelper` now resolves static function-call names through PHPStan's `ReflectionProvider`. Ordinary
unqualified `__()`, `trans()`, and `trans_choice()` calls inside a namespace therefore follow PHP's global-function
fallback and reach every call rule and collector. Calls that resolve to a function actually declared in the namespace
remain outside the Laravel-helper contract. Unit coverage locks the global fallback, while the end-to-end fixture also
locks the namespaced-override boundary in a complete PHPStan process.

This completed the first of the six reported items.

### Countable translation choice inputs

**Status:** Complete for item 2 of GitHub issue #6.

`InvalidChoiceRule` now mirrors Laravel's `Translator::choice()` boundary by converting array and `Countable` members
of the inferred number type to their possible counts before checking explicit condition coverage. Arrays retain
PHPStan's inferred size, including exact and non-empty bounds; other `Countable` objects use `int<0, max>`. The
conversion preserves non-countable union members, so valid counted inputs no longer emit `missingCase` while a counted
collection still requires every possible count. Rule coverage includes fixed-size, empty, non-empty, and general lists,
`Countable`, an `ArrayAccess&Countable` intersection, and a union of counted and numeric inputs.

This completed the second of the six reported items. The issue's separate, lower-priority observation about redundant
coverage errors for inputs rejected by PHPStan remains unaddressed.

### Laravel vendor translation namespaces

**Status:** Complete for item 3 of GitHub issue #6.

Translation discovery now recognizes Laravel's `vendor/<namespace>/<locale>/<group>.php` layout and stores each file
under its captured namespace. Namespaced keys remain isolated from ordinary groups and from other vendor namespaces;
locale discovery, fuzzy candidates, missing-translation checks, unused-key reconstruction, source files, source lines,
and deterministic file ordering use the same callable key. Unsupported vendor JSON and deeper nested paths remain
outside the loader's contract. Tests cover two namespaces, two locales, a per-locale missing key, and an unused
namespaced key.

### Nested base-locale key classification

**Status:** Complete for item 4 of GitHub issue #6.

The base-locale heuristic now recognizes grouped keys with one or more dot-separated identifier segments, including
vendor-namespaced keys. Nested keys such as `validation.custom.email.required` therefore receive the same
`missingBaseLocaleTranslationString` diagnostic as a one-level key. Sentence-like JSON keys and malformed grouped keys
remain outside the heuristic. Focused coverage locks ordinary nested keys, hyphenated and underscored segments, vendor
namespaces, sentences, and malformed separators.

The remaining issue #6 slices are suppression of plural-form diagnostics for missing grouped keys and removal of
identical fuzzy suggestions. The separate lower-priority invalid-input diagnostic observation also remains
unaddressed.

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

### BookStack external canary

**Status:** Complete as an optional, manually dispatched integration check.

`composer bookstack:canary` clones BookStack `v26.05.3` at its verified commit and installs this extension from an
extracted Composer archive without a source symlink. Minimal-change resolution preserves the application's locked
PHPStan 2.2.6, Larastan 3.10.0, and Laravel 12.64.0 versions while adding pinned BladeStan 0.11.7 and Livewire 4.4.0. The
canary asserts the clean stock baseline and application-only result before filtering BladeStan's unrelated
compiled-template findings from curated extension identifiers, tips, known regression absences, and a broad diagnostic
count guard. Its opt-in plural-completeness pass separately observes 89 findings within a 70-through-110 range,
including curated assertions for the Icelandic missing delimiter and validation of `de_informal` through its configured
alias. The networked check remains outside normal pull requests and `composer check:full`.

### BookStack replacement triage

**Status:** Complete. The 51 raw unused-replacement reports were classified as 47 unique outer call-site diagnostics
and 38 unique locale/key/replacement tuples. Thirty-five are probable localized placeholder drift, three are plausible
locale-specific omissions where the caller necessarily supplies a superset, and none is a caller-wide dead argument.

Four exact duplicate diagnostics came from repeated nested analysis at one Blade call. The bridge now deduplicates
identical structured diagnostics while preserving findings from distinct outer view calls. Unit coverage locks the
queue behavior, and the pinned BookStack canary rejects exact duplicate extension diagnostics while retaining curated
examples of whitespace, case, and locale-specific omission findings.

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
   threshold. **Complete:** the reviewed 82.49% baseline now has an enforced
   80% overall and covered-code floor.
8. Evaluate targeted Eris properties after locale canonicalization is stable.
   **Complete:** an isolated PHP 8.1/PHPUnit 10 job checks four deterministic
   locale and translation-key properties with Eris 1.1.

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
