# Repository review — phpstan-lost-in-translation

**Reviewed at:** commit `3dc9e7b` (`Include fileless base locale in analysis`) on branch `develop`<br>
**Date:** 2026-08-10<br>
**Scope:** Full `src/`, extension wiring (`extension.neon`, `composer.json`), related tests and docs<br>
**Method:** Manual source review of loader, helper, call rules, fuzzy search, unused-translation pipeline, and error formatter; cross-check against `docs/code-review-remediation-plan.md`; targeted runtime probes; automated verification below.

## Verification performed

| Check | Result |
| --- | --- |
| `vendor/bin/phpunit` (no coverage) | **83 tests, 140 assertions**, 1 skipped — OK |
| `vendor/bin/phpstan analyse` (project config) | **No errors** |
| Targeted probes | Confirmed CR-08 double-count, CR-10 empty-set warning, script-subtag locale false negatives, `NaiveFuzzyStringSet` empty-query `DivisionByZeroError` under contract violation |

Automated suites are green on the currently tested paths. Findings below are mostly **uncovered edge cases**, packaging gaps, and residual items from the earlier remediation plan.

## Executive summary

The package is in good shape on its primary Laravel translation workflows: named-argument binding, namespaced key caching, flexible region locales (`pt-BR` / `EN-us`), fileless base-locale inclusion, and nested-path skipping all look correct and are covered by tests. PHPStan and PHPUnit are clean.

Remaining risk concentrates in four areas:

1. **Locale canonicalization soundness** for BCP 47 script subtags (`zh_Hans`, `sr_Latn_RS`) under flexible mode.
2. **Call-rule false positives** (replacement variant counting; fileless-base null values fed into replacement/choice analysis).
3. **Production packaging** (undeclared runtime deps for the JSON formatter / Fuse backend / `ctype`).
4. **Optional / diagnostic defects** (encoding message text, empty `MyFuzzyStringSet`, incomplete vendor-namespace layout support).

**Counts:** 3 bugs, 5 suggestions, 6 nits/quality notes (plus intentional product gaps called out separately).

## Findings summary

| ID | Severity | Area | Summary |
| --- | ---: | --- | --- |
| RV-01 | bug | Locale handling | Flexible `canonicalizeLocale` uppercases the entire subtag tail, breaking ICU script locales (`zh_Hans` → `zh_HANS`). |
| RV-02 | bug | Replacements | Uppercase-only replacement keys (`FOO`) are counted three times and reported as multi-variant (CR-08). |
| RV-03 | bug | Diagnostics | Invalid-value encoding errors format the key instead of the value (CR-09). |
| RV-04 | suggestion | Call rules | Fileless base injection yields `(base, null)` candidates; replacement/choice rules use `$value ?? $key` and can emit extra noise. |
| RV-05 | suggestion | Packaging | Runtime uses `nette/utils`, optional `loilo/fuse`, and `ext-ctype` without matching production requirements (CR-07). |
| RV-06 | suggestion | Loading | Locale-spelling collisions drop all further files under the losing spelling after a single diagnostic. |
| RV-07 | suggestion | Loading | Laravel vendor / namespaced translation paths are still unsupported (CR-02 remainder). |
| RV-08 | suggestion | Fuzzy | Empty `MyFuzzyStringSet` search emits an undefined-key warning (CR-10). |
| RV-09 | nit | Encoding rule | Same encoding check exists in `PhpLoader` and `InvalidCharacterEncodingRule` with slightly different messaging. |
| RV-10 | nit | Helper | Blade compiled path may surface as the reported file (`@TODO` in helper). |
| RV-11 | nit | Loading | `JsonLoader::buildLineNumberMap` does not close the fopen handle. |
| RV-12 | nit | AST visitor | `KeyLineNumberVisitor` still uses deprecated `Node\Expr\ArrayItem` and `jsonSerialize()['value']`. |
| RV-13 | nit | Types | `PhpLoader::load` is annotated as returning `mixed` though it always returns `LoadResult`. |
| RV-14 | nit | Locations | `SplFileInfo::getRealPath()` result is stored without a `false` guard. |

## Detailed findings

### RV-01 — Flexible locale canonicalization breaks script subtags

**Severity:** bug<br>
**Location:** `src/Utils.php` (`canonicalizeLocale`, `checkLocaleExists`)

With `strictLocales: false` (the default), every substring after the first `_` is uppercased:

```php
return strtolower($language) . '_' . strtoupper($territory);
```

That is correct for `language_REGION` (`pt-br` → `pt_BR`) but incorrect for BCP 47 **script** subtags, which ICU/CLDR expect in title case:

| Input | Canonical (flexible) | `Locales::exists(input)` | `Locales::exists(canonical)` / `checkLocaleExists` |
| --- | --- | --- | --- |
| `zh_Hans` | `zh_HANS` | yes | **no** |
| `sr_Latn_RS` | `sr_LATN_RS` | yes | **no** |
| `pt-br` | `pt_BR` | n/a | yes |

**Impact:**

- `InvalidLocaleRule` and `TranslationLoaderErrorRule` report **unknown locale** for valid script locales when flexible mode is on.
- Paradoxically, **strict mode accepts** these identifiers (no rewrite), so the “loose” mode is stricter for this class of locales.
- Lookup still works *within* the analyzer if both disk spelling and call site go through the same broken rewrite, but validation against Symfony Intl is wrong, and mixed spellings (`zh_Hans` vs `zh_HANS` on disk) become fragile.

**Suggestion:** Canonicalize by subtag role (language lower, 4-letter script title case, 2-letter region upper, numeric regions unchanged), or validate with ICU using a form that `Locales::exists` accepts before rewriting. Add fixtures for `zh_Hans` / `sr_Latn` under flexible and strict modes.

---

### RV-02 — Replacement variant double-count (CR-08)

**Severity:** bug<br>
**Location:** `src/CallRule/InvalidReplacementRule.php` (~74–76)

The rule counts presence of `ucfirst`, `mb_strtoupper`, and raw forms independently:

```php
$replaceVariantCount =
    (int) str_contains($value, ':' . self::ucfirst($search))
    + (int) str_contains($value, ':' . mb_strtoupper($search, 'UTF-8'))
    + (int) str_contains($value, ':' . $search);
```

For `$search = 'FOO'`, all three placeholders are `':FOO'`, so a single legitimate placeholder yields count `3` and fires `invalidReplacement.multipleVariants`.

**Reproduced:** `FOO` → count 3 against value `":FOO is here"`.

**Suggestion:** Deduplicate the three candidate placeholder strings before counting. Cover lower, title, upper, and multibyte keys.

---

### RV-03 — Invalid-value encoding message shows the key (CR-09)

**Severity:** bug<br>
**Location:** `src/CallRule/InvalidCharacterEncodingRule.php` (~49–52)

When a translation **value** fails UTF-8 checks, the human-readable message still formats `$key`:

```php
'Invalid character encoding for value %s in locale %s',
Utils::e($key),  // should be $value
Utils::e($locale),
```

Metadata carries the correct value; the message does not. `PhpLoader` already formats the value correctly for load-time checks — only the call-site rule is wrong.

**Suggestion:** Pass `$value` to `Utils::e()`, with a fixture where key and value encodings differ.

---

### RV-04 — Fileless base locale broadens replacement/choice analysis

**Severity:** suggestion<br>
**Location:** `LostInTranslationHelper::gatherPossibleTranslations`, `InvalidReplacementRule`, `InvalidChoiceRule`

CR-06 correctly adds a configured base locale with no on-disk files to implicit lookup so `MissingTranslationStringInBaseLocaleRule` can fire. That same candidate list is shared by all call rules. For a missing key, candidates include `(baseLocale, null)`, and replacement/choice rules fall back with `$value ?? $key`, analyzing the raw key string as if it were the base-locale translation.

**Impact:** Apps that keep English (or another base) as key text with only other locales on disk can newly see unused/multiple-variant replacement or choice diagnostics against the key for the fileless base, even when the user never intended base-locale replacement analysis.

**Suggestion:** Skip `null` values in replacement/choice rules, or split “locales for presence checks” from “locales with real translation text.” If the broader behavior is intentional, document it and add a regression test so noise level is explicit.

---

### RV-05 — Incomplete production dependencies (CR-07)

**Severity:** suggestion<br>
**Location:** `composer.json`, `JsonErrorFormatter`, `FuseFuzzyStringSet`, `Utils::escapeBinary`

| Dependency | Used by | Declared |
| --- | --- | --- |
| `nette/utils` (`Nette\Utils\Json`) | JSON error formatter | **not** in `require` |
| `loilo/fuse` | `FuseFuzzyStringSet` under `src/` | `require-dev` only |
| `ext-ctype` (`ctype_print`) | `Utils::escapeBinary` | **not** declared |

A consumer `composer install --no-dev` can remove Fuse and may not install Nette; PHPStan’s PHAR does not expose unprefixed `Nette\Utils\Json`. Selecting the custom formatter or constructing the Fuse backend can then fail at runtime.

**Suggestion:** Require `nette/utils` and `ext-ctype` (or remove those APIs), and either promote `loilo/fuse` to runtime or move `FuseFuzzyStringSet` out of autoloaded production `src/`. Add a `--no-dev` smoke load of registered services.

---

### RV-06 — Collision handling drops secondary files quietly

**Severity:** suggestion<br>
**Location:** `src/TranslationLoader/TranslationLoader.php` (scan loop, locale conflict branch)

When two on-disk spellings canonicalize to the same key, the first spelling in deterministic path order wins and each **alias spelling** gets one `IDENTIFIER_LOCALE_CONFLICT` diagnostic. Further files under the losing spelling are `continue`d with no additional diagnostic and without parse/value scanning.

A mixed layout such as `pt-BR.json` + `pt_BR/messages.php` therefore keeps only one side after a single conflict message; group PHP under the loser is never inspected for invalid values.

**Suggestion:** State in the diagnostic that all files for the losing spelling are ignored (optionally list paths), or emit one diagnostic per skipped path.

---

### RV-07 — Vendor / namespaced translation paths unsupported

**Severity:** suggestion (product gap)<br>
**Location:** `TranslationLoader::scan` path regex `^([\w-]{2,})(?:\.json|/([^/]+)\.php)$`

Only root JSON locales and one-level `locale/group.php` layouts are loaded. Laravel vendor overrides (`vendor/package/locale/*.php` or namespaced paths) are skipped after CR-02’s safety fix. Namespace is always stored as `'*'`; `parseKey()` understands `vendor::group.item` for *lookups*, but discovery never fills those namespaces from disk.

**Impact:** Correct for avoiding CR-02 crashes; incorrect for apps that rely on package language files or namespaced groups unless they copy keys into app `lang/`.

**Suggestion:** Explicitly document the supported layout; if vendor support is desired, parse namespace/locale/group intentionally with dedicated fixtures.

---

### RV-08 — Empty `MyFuzzyStringSet` warning (CR-10)

**Severity:** suggestion<br>
**Location:** `src/Fuzzy/MyFuzzyStringSet.php` (~128–131)

```php
$smallestDelta = $otherIndexDeltas[array_key_first($otherIndexDeltas)];
```

On an empty set (or no candidates), `array_key_first` is `null` and indexing emits `Undefined array key ""` before returning `null`. Default runtime uses `NaiveFuzzyStringSet`, so production impact is low unless this implementation is selected.

**Suggestion:** Early-return `null` when `$otherIndexDeltas` is empty; assert search results in unit tests (not only benchmarks).

---

### RV-09 — Dual encoding checks with divergent messages

**Severity:** nit<br>
**Locations:** `PhpLoader` (load-time), `InvalidCharacterEncodingRule` (call-time)

Load-time checks report values correctly; call-time checks are wrong for values (RV-03) and only see keys/values present in `possibleTranslations`. Overlap is fine, but messaging and coverage should stay aligned.

---

### RV-10 — Reported file may be a compiled Blade path

**Severity:** nit<br>
**Location:** `src/LostInTranslationHelper.php` (~164)

```php
$file = $scope->getFile(); // @TODO this might be getting the compiled blade path...
```

Unused-string collection already special-cases `blade-compiled` via the fake collector rule, but diagnostics on Blade-derived calls can still point at compiler output rather than the `.blade.php` source. Worth tracking for UX.

---

### RV-11 — JSON line-map file handle leak

**Severity:** nit<br>
**Location:** `src/TranslationLoader/JsonLoader.php` (`buildLineNumberMap`)

`fopen` is never paired with `fclose`. Impact is small (analysis-time, finite lang files) but easy to fix with `try/finally`.

---

### RV-12 — Deprecated php-parser node usage in key visitor

**Severity:** nit<br>
**Location:** `src/TranslationLoader/KeyLineNumberVisitor.php`

Still uses `Node\Expr\ArrayItem` (alias of `Node\ArrayItem`) and reads scalar values via `jsonSerialize()['value']` for PHP-Parser 4/5 compatibility. Prefer typed `Scalar\Int_` / `Scalar\String_` (and version-appropriate int nodes) with `->value` once the supported php-parser range allows it.

---

### RV-13 — `PhpLoader::load` return type

**Severity:** nit<br>
**Location:** `src/TranslationLoader/PhpLoader.php`

Signature is `load(...): mixed` while every path returns `LoadResult`. Tighten to `LoadResult` for static analysis and readers.

---

### RV-14 — Unchecked `getRealPath()`

**Severity:** nit<br>
**Location:** `src/TranslationLoader/TranslationLoader.php` (~443)

Location records store `$file->getRealPath()`, which may be `false`. Unused-translation diagnostics then receive a non-string file path. Prefer `getPathname()` fallback when `getRealPath()` fails.

---

## Additional observations (not filed as issues)

### Strengths

- **CR-01–CR-06 core fixes land correctly:** missing lang dir handling, path-match gating, named args, key-cache isolation, flexible region locales, fileless base inclusion.
- **Stable sorting** of locales and keys keeps diagnostics deterministic.
- **WeakMap caching** of parsed calls avoids re-work per rule without leaking across scopes.
- **Conditional DI tags** for optional rules/collectors are clear and match README flags.
- **Test surface is solid** (83 tests including locale collisions, flexible base, named args, loader edge cases). PHPStan at max project level is clean.

### Intentional / accepted behaviors

- **Base locale missing strings are not “missing translations”** — Laravel falls back to the key; only the “likely untranslated identifier key” rule targets the base locale.
- **`PhpLoader` uses `require`** — matches Laravel’s loader and can execute side effects; language paths are trusted project assets.
- **Collector serialization via `unserialize`** — constrained to PHPStan’s collector pipeline; not a public network boundary.
- **Simple `singular|plural` choices** skip range coverage analysis by design (Laravel `MessageSelector` special case).

### Fuzzy search contract

`FuzzyStringSetInterface::search` is documented as `non-empty-string`. Call sites guard with `strlen($key) > 0`. Passing `''` into `NaiveFuzzyStringSet` / Fuse backends raises `DivisionByZeroError`. No change required if the contract is enforced; a defensive early return would still be cheap hardening.

### Style and hygiene

- Copyright headers and `declare(strict_types=1)` are consistent across `src/`.
- `extension.neon` mixes tabs (`conditionalTags`) and spaces (services/parameters) — cosmetic.
- Root `.gitignore` already excludes `coverage/`, `*.log`, `clover.xml`; keep generated artifacts untracked.
- `docs/code-review-remediation-plan.md` still describes some items with “Current behavior” past-tense confusion after completion; worth a doc pass when closing CR-07–CR-10.
- Empty `tests/lang-locale-case-collision/` directory remains (case-only fixture approach was likely abandoned for portability — good; remove the empty dir if unused).

### Choice analysis nuance

`InvalidChoiceRule` casts range bounds with `(int)`, so fractional bounds in ICU-style segments are truncated. Uncommon in Laravel apps; note if supporting full MessageSelector syntax later. Negative-number coverage is an open `@TODO` and can false-positive if only non-negative segments are defined.

## Relation to prior remediation plan

| Prior ID | Status in this review |
| --- | --- |
| CR-01 missing lang dir | Fixed |
| CR-02 path match | Fixed for safety; vendor layout still out of scope (RV-07) |
| CR-03 named arguments | Fixed |
| CR-04 key cache | Fixed |
| CR-05 flexible locales | Fixed for region form; **regressed/incomplete for script subtags (RV-01)** |
| CR-06 fileless base | Fixed; watch cross-rule noise (RV-04) |
| CR-07 packaging | Open (RV-05) |
| CR-08 replacements | Open (RV-02) |
| CR-09 encoding message | Open (RV-03) |
| CR-10 empty fuzzy set | Open (RV-08) |

## Suggested fix order

1. **RV-02 / RV-03** — small pure bugfixes with immediate diagnostic quality wins.
2. **RV-01** — locale script/region canonicalization; blocks correct flexible validation for CJK and similar locales.
3. **RV-05** — packaging so production installs match shipped code.
4. **RV-04 / RV-06** — reduce false positives and silent data loss.
5. **RV-08 and nits** — optional backends and polish.
6. **RV-07** — product decision on vendor namespaces (document or implement).

## Definition of a healthy follow-up

- Regression tests for each RV-01–RV-03 (and RV-08 if kept in tree).
- `composer install --no-dev` smoke that constructs the DI services used by `extension.neon`.
- Existing PHPUnit / PHPStan / e2e / matrix CI remain green.
- README notes any user-visible change (script locales, replacement rules, packaging).
