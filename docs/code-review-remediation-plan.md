# Code Review Remediation Plan

Reviewed against commit `692b6eb4d4d585b2f8447896e60be93a9acda34a` on 2026-08-09.

## Executive summary

The full codebase review found ten reproducible issues:

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
4. Some shipped source code relies on dependencies absent from a production
   Composer installation.

## Findings summary

| ID | Priority | Area | Summary |
| --- | --- | --- | --- |
| CR-01 | P1 | Translation loading | A missing language directory throws during container construction. |
| CR-02 | P1 | Translation loading | Files whose relative paths do not match the expected pattern are still processed. |
| CR-03 | P2 | Call parsing | Named arguments are assigned by position rather than parameter name. |
| CR-04 | P2 | Key parsing | Namespaced keys are cached under their normalized plain key. |
| CR-05 | P2 | Locale handling | Flexible locale validation is not applied to translation lookup. |
| CR-06 | P2 | Base locale | The base locale is not analyzed when it has no translation files. |
| CR-07 | P2 | Packaging | Shipped classes use undeclared or development-only runtime dependencies. |
| CR-08 | P2 | Replacements | Equivalent placeholder variants are counted more than once. |
| CR-09 | P3 | Diagnostics | Invalid-value encoding errors display the translation key. |
| CR-10 | P3 | Fuzzy search | Searching an empty `MyFuzzyStringSet` emits an undefined-key warning. |

## Detailed findings and remediation

### CR-01: Missing language directories crash analysis

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

**Location:** `src/Utils.php:62`,
`src/TranslationLoader/TranslationLoader.php:121`,
`src/TranslationLoader/TranslationLoader.php:142`

**Current behavior:**

With `strictLocales: false`, locale validation normalizes case and replaces
dashes with underscores. `TranslationLoader::hasLocale()` and `get()` still use
the original locale string as an exact array key.

For example, `JA` passes flexible locale validation, but a call using it emits
"no available translation strings" and "missing translation" even when the
`ja` locale contains the requested key.

**Impact:**

- Documented flexible locale forms cause false positives.
- Base-locale comparisons can also fail because they are exact.

**Proposed remediation:**

1. Introduce one locale canonicalization function used by validation,
   discovery, base-locale comparison, `hasLocale()`, and `get()`.
2. Wire `strictLocales` into the lookup layer, or build a canonical alias map
   during scanning.
3. Detect and define behavior for two discovered locale directories that
   canonicalize to the same identifier.

**Regression tests:**

- Case variants such as `JA` and `ja`.
- Dash/underscore variants for a regional locale.
- Strict mode continues to require an exact identifier.
- Canonicalization collisions produce deterministic diagnostics.

### CR-06: A fileless base locale is excluded

**Location:** `src/LostInTranslationHelper.php:234`

**Current behavior:**

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

**Location:** `composer.json:14`,
`src/ErrorFormatter/JsonErrorFormatter.php:28`,
`src/Fuzzy/FuseFuzzyStringSet.php:22`, `src/Utils.php:142`

**Current behavior:**

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

**Proposed remediation:**

1. Add `nette/utils` and `ext-ctype` to runtime requirements, or remove those
   dependencies from runtime code.
2. Either move `loilo/fuse` to runtime requirements or move
   `FuseFuzzyStringSet` to benchmark/test-only code.
3. Add a minimal `composer install --no-dev` smoke test that loads all
   registered services and exercises the custom formatter.

### CR-08: Equivalent replacement variants are double-counted

**Location:** `src/CallRule/InvalidReplacementRule.php:74`

**Current behavior:**

The rule independently checks ucfirst, uppercase, and original placeholders.
For keys such as `FOO`, these transformations are identical, so one `:FOO`
placeholder is counted three times and reported as matching multiple variants.

**Impact:**

- Valid replacement arrays generate false-positive diagnostics.
- Mixed-case keys such as `Foo` can have the same problem.

**Proposed remediation:**

Generate the three placeholder strings, deduplicate them, and then count the
distinct variants present in the translation value.

**Regression tests:**

- Lowercase, title-case, and uppercase replacement keys.
- One valid placeholder versus genuinely distinct variants in the same value.
- Multibyte replacement keys.

### CR-09: Invalid-value diagnostics display the key

**Location:** `src/CallRule/InvalidCharacterEncodingRule.php:51`

**Current behavior:**

When the translation value contains invalid encoding, the diagnostic formats
`$key` instead of `$value`. The metadata contains the correct value, but the
human-readable message does not.

**Proposed remediation:**

Pass `$value` to `Utils::e()` and add a fixture whose valid key and invalid
value differ.

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

## Additional maintenance observations

### PHPUnit configuration schema

The test suite passes but PHPUnit reports that `phpunit.xml.dist` validates
against a deprecated schema. Because the project supports PHPUnit 9 through 11,
the migration should verify the chosen configuration remains accepted across
the full matrix.

### Development dependency advisories

`composer audit --locked` reports three advisories against the locked Laravel
10 development baseline. Laravel 10 is retained to exercise PHP 8.1
compatibility, and `composer audit --locked --no-dev` is clean.

Recommended follow-up:

- Run `composer audit --locked --no-dev` in CI for distributable dependencies.
- Document or configure explicit exceptions for unavoidable development-only
  compatibility fixtures.
- Continue testing supported maintained Laravel versions separately from the
  PHP 8.1 compatibility baseline.

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
2. Migrate the PHPUnit configuration with matrix verification.
3. Add runtime-only Composer auditing to CI.

## Definition of done

- Every CR item has a focused regression test that fails before its fix.
- The full PHP and Laravel compatibility matrix remains green.
- `composer install --no-dev` can load and exercise every registered runtime
  service.
- Composer validation, PHPCS, PHPStan, PHPUnit, end-to-end tests, benchmarks,
  actionlint, and `nix flake check -L` pass.
- No new PHP warnings, deprecations, or ignored static-analysis errors are
  introduced.
- User-visible behavior changes are reflected in `README.md` where applicable.
