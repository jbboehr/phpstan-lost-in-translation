# BookStack integration report

**Tested:** 2026-08-09 (America/Los_Angeles; 2026-08-10 UTC)<br>
**Extension:** `phpstan-lost-in-translation` at `a03812b` (`Deduplicate replacement placeholder variants`)<br>
**Application:** BookStack `v26.05.3` at `e1cd3229966d939a75a74a2224ff0643d8af337b`<br>
**Scope:** Real-application validation of translation loading and call analysis, including compiled Blade templates through BladeStan<br>
**Status:** Investigation complete; no BookStack or extension source changes were retained from the experiment

## Executive summary

BookStack is a useful real-world canary for this extension. Its application-only PHPStan baseline was clean, its translation corpus is large and varied, and enabling this extension exposed both concrete package defects and likely defects in BookStack's translations.

The main findings are:

1. The PHP translation loader reports every conventional empty array as an invalid translation value. BookStack's `validation.attributes` array produces 53 false positives, one per locale.
2. Choice-string validation is narrower than Laravel's actual `MessageSelector` behavior. Valid one-form and locale-specific three-form strings are reported as malformed.
3. BladeStan and Larastan are compatible. The initial internal errors came from a BookStack-specific container binding: resolving `BookStack\App\Application` constructed a second, unbooted application. Binding the concrete application class to the existing instance fixed analysis.
4. BladeStan preserves this extension's error identifier and message, but replaces its metadata and reports the controller's `view()` call as the outer location. This makes locale-specific translation findings harder to triage.
5. The run found four high-confidence BookStack translation defects: an Indonesian placeholder typo, an empty Portuguese translation, malformed Slovak range syntax, and a likely missing Icelandic choice delimiter.
6. Fuzzy search did not materially affect the application-only run time. The earlier concern that fuzzy matching would be too slow was not reproduced on this corpus with the current implementation.

The recommended order is to fix empty-array loading first, then align choice validation with Laravel. Once those two sources of false positives are addressed, BookStack would be a strong candidate for a repeatable, optional integration smoke test.

## Follow-up automation

**Implemented 2026-08-11.** `composer bookstack:canary` now reproduces the integration against the same BookStack tag and
commit in a temporary checkout. It builds and extracts this extension's Composer archive before installing the artifact
without a source symlink. It then verifies the clean stock baseline, the application-only locale-alias result, and a
BladeStan 0.11.7 pass with curated translation identifiers and preserved bridge tips. Non-extension BladeStan findings
are filtered by the assertion layer instead of ignored in PHPStan configuration.

Minimal-change dependency resolution preserves BookStack's PHPStan 2.2.6, Larastan 3.10.0, and Laravel 12.64.0 lock
versions while adding pinned BladeStan 0.11.7 and Livewire 4.4.0. The canary asserts these versions and prints them in
its result so dependency drift fails separately from diagnostic drift. The temporary dependency graph also lifts
development-only PHP_CodeSniffer from vulnerable version 4.0.1 to fixed version 4.0.2 without invoking the tool.

The refreshed Blade pass reports 62 extension diagnostics: seven missing-choice cases, two non-numeric choice
conditions, one unknown application locale, 51 unused replacements, and one missing translation. The former 53
empty-array loader errors and 35 malformed-choice reports remain absent. This count is recorded for visibility; the
canary asserts selected stable findings, regression absences, and a broad 40-through-100 count range rather than
freezing every mixed-confidence diagnostic.

The external check is manually dispatched through `.github/workflows/bookstack.yml`. It is intentionally not part of
the normal pull-request or `composer check:full` gate.

## Objectives

The experiment was designed to answer five questions:

- Can the extension be installed into a current, substantial Laravel application without replacing that application's existing PHPStan setup?
- Does the extension find useful translation defects outside its fixture suite?
- Does it introduce false positives at a scale that would prevent adoption?
- Can compiled Blade translation calls be analyzed alongside Larastan?
- Is fuzzy lookup fast enough to leave enabled in a representative application?

This was an exploratory integration run, not an upstream BookStack audit and not a stable benchmark.

## Test environment

| Component | Version or revision |
| --- | --- |
| Host PHP | PHP 8.4 from this repository's Nix `php84` shell |
| Extension | `a03812bc8e98f6c4bcec80c9ac40182fa5cb4184` |
| BookStack | `v26.05.3`, peeled commit `e1cd3229966d939a75a74a2224ff0643d8af337b` |
| PHPStan | 2.2.8 |
| Larastan | 3.10.0 |
| BladeStan | 0.11.7 |
| Laravel | 12.65.0 after the BladeStan dependency update |
| Translation locales | 53 directories |
| Translation files | 689 |
| Approximate translation-related call sites | 1,706 lexical matches across application and view sources |

The test used a temporary clone and a Composer path repository with `symlink: false`, so BookStack analyzed a copied snapshot of this extension rather than the working tree. The temporary checkout was deleted after the investigation.

Installing BladeStan required a Composer update with dependencies. That moved Laravel from 12.64.0 to 12.65.0 and installed Livewire 4.3.5, among other transitive changes. Results therefore describe the resolved test environment above, not BookStack's completely untouched lock file.

## Method

### 1. Establish the BookStack baseline

BookStack's distributed PHPStan configuration already included Larastan, analyzed `app/` at level 4, and loaded `bootstrap/phpstan.php`. The unmodified application analysis was run before adding this extension.

Result: no global or file errors, in approximately six seconds.

### 2. Add this extension

The local extension snapshot was installed through a Composer path repository. The test configuration used BookStack's `lang/` tree and English as its base locale:

```neon
parameters:
    lostInTranslation:
        langPath: lang
        baseLocale: en
        fuzzySearch: true
```

The application-only pass was tested with fuzzy search both disabled and enabled. Loader-error reporting was later disabled temporarily to separate loader noise from call-site analysis:

```neon
parameters:
    lostInTranslation:
        translationLoaderErrors: false
```

This suppression was diagnostic only. It did not make the empty-array behavior correct.

### 3. Add BladeStan

BladeStan 0.11.7 was installed to make translation calls in `.blade.php` templates visible to PHPStan. BladeStan and Larastan are designed to run together; the internal errors encountered during setup were not a general incompatibility between them.

The first BladeStan pass failed while resolving Laravel services such as `view`, `config`, and `command.tinker`. Instrumentation showed that compiled Blade code caused PHPStan to evaluate this expression:

```php
resolve(BookStack\App\Application::class)
```

BookStack had not bound its concrete application class to the existing application instance. Laravel therefore constructed a second `BookStack\App\Application` with no base path, replaced the global container with that unbooted instance, and left core service bindings unavailable.

The temporary test-only bootstrap workaround was:

```php
$app = app();
$app->instance($app::class, $app);
```

After that binding was added to BookStack's PHPStan bootstrap, Blade analysis completed without global or internal errors.

### 4. Separate extension findings from BladeStan findings

BladeStan's nested PHPStan pass also reported ordinary type errors in compiled templates. To evaluate this extension independently, JSON output was filtered after analysis to identifiers beginning with `lostInTranslation.`.

This was reporting-layer filtering. BladeStan's own diagnostics were still generated, and no broad PHPStan ignore rule was added to either repository.

## Results

| Run | Global errors | File diagnostics | Translation diagnostics | Approximate time |
| --- | ---: | ---: | ---: | ---: |
| BookStack baseline, `app/` only | 0 | 0 | 0 | 6 s |
| Extension, `app/`, fuzzy off | 0 | 54 | 54 | 7 s |
| Extension, `app/`, fuzzy on | 0 | 54 | 54 | 7 s |
| Extension, loader errors disabled, `app/` | 0 | 0 | 0 | 7 s |
| Extension plus BladeStan, reachable views | 0 | 1,202 | 96 | 12 s |

The full BladeStan result contained 1,106 non-translation diagnostics and 96 diagnostics from this extension. The 96 translation diagnostics were:

| Identifier | Count |
| --- | ---: |
| `lostInTranslation.invalidChoice.malformed` | 35 |
| `lostInTranslation.invalidChoice.missingCase` | 7 |
| `lostInTranslation.invalidChoice.nonNumeric` | 2 |
| `lostInTranslation.invalidReplacement.unused` | 51 |
| `lostInTranslation.missingTranslationString` | 1 |
| **Total** | **96** |

The Blade pass had `translationLoaderErrors: false`, so the 53 empty-array errors and custom-locale error from the application-only pass are not included in this table.

## Extension findings

### BS-EXT-01: Empty translation arrays create a false-positive flood

**Classification:** Confirmed extension defect<br>
**Impact:** High for adoption<br>
**Area:** `src/TranslationLoader/PhpLoader.php`

Every BookStack locale contains the conventional Laravel validation entry:

```php
'attributes' => [],
```

The loader flattens translation files in a manner similar to `Arr::dot()`. Empty arrays remain leaf values, after which the load loop rejects them because they are not strings. BookStack consequently receives 53 `lostInTranslation.translationLoaderError` diagnostics with `Invalid value: []`, one for every locale's `validation.php`.

An empty array is a valid structural placeholder in Laravel translation files. It represents a group with no child strings, not an invalid translation string. Reporting it obscures actual loader failures and makes the default configuration unusable on this otherwise conventional corpus.

**Recommendation:** Ignore empty arrays while flattening or before scalar validation. Continue reporting non-empty arrays that survive flattening unexpectedly, objects, resources, and other unsupported leaf values. Add fixtures covering:

- an empty top-level group;
- an empty nested group;
- valid strings adjacent to an empty group;
- a genuinely invalid non-string leaf.

### BS-EXT-02: Choice validation rejects valid Laravel choice forms

**Classification:** Confirmed extension defect<br>
**Impact:** High false-positive rate<br>
**Area:** `src/CallRule/InvalidChoiceRule.php`

The current rule accepts either:

- segments with explicit `{...}` or `[...]` conditions; or
- exactly two unconditioned singular/plural segments.

Laravel's `MessageSelector::choose()` is more permissive. It accepts a single unconditioned segment and locale-specific plural indexes over three or more unconditioned segments. A one-segment Japanese, Thai, or Chinese string is valid. Three-form Russian strings are also valid when Laravel selects a locale-specific plural index.

This mismatch accounts for many of the 35 `invalidChoice.malformed` diagnostics. It also mixes genuine syntax defects with valid localized plural forms, lowering confidence in the entire rule.

**Recommendation:** Base validation on Laravel's actual parsing and selection rules:

1. Parse explicit conditions the same way Laravel does.
2. Permit one or more unconditioned segments.
3. When a locale is known, validate segment availability against Laravel's plural-index behavior for that locale.
4. Keep malformed condition syntax, invalid ranges, and unreachable selections as separate identifiers.
5. Add fixture coverage for one-form locales, ordinary two-form locales, three-form locales, explicit exact values, explicit ranges, and mixed conditional/fallback forms.

### BS-EXT-03: BladeStan wrapping loses diagnostic metadata

**Classification:** Integration/diagnostic limitation<br>
**Impact:** Medium<br>
**Areas:** BladeStan nested analysis; this extension's error metadata and formatter

BladeStan preserves the nested error's identifier and human-readable message, but replaces this extension's metadata with its own template location fields:

- `template_file_path`
- `template_line`

The original translation key, locale, and value metadata are no longer available to the outer analysis, and the extension's tip is also lost. The reported file and line point to the controller's `view()` call, while the actual translation call is available only in BladeStan's template metadata.

In a focused analysis of `BookController`, for example, the extension reported an unused `bookName` replacement at the controller's line 215. The underlying call was in `resources/views/books/delete.blade.php:19`.

This is consistent with the Blade-path concern previously recorded as RV-10, but the experiment narrows the behavior: compiled-template source information exists, yet it is represented by BladeStan metadata rather than the normal PHPStan error location.

**Recommendation:** Investigate whether BladeStan offers an extension point or stable metadata contract that can preserve nested error metadata while promoting the template file and line. At minimum, document the behavior and add a BladeStan integration fixture that asserts the identifier and template location. Avoid coupling directly to an undocumented metadata shape without coordinating with BladeStan.

### BS-EXT-04: Application-specific locale aliases cannot be configured

**Classification:** Product gap, not necessarily a defect<br>
**Impact:** Low to medium depending on application<br>
**Area:** Locale validation

BookStack intentionally uses `de_informal`. Its application maps that identifier to `de_DE`, and its locale handling explicitly accommodates non-standard application locales. Symfony Intl does not recognize `de_informal`, so this extension reports:

```text
lostInTranslation.invalidLocale.unknown
```

The diagnostic is consistent with the extension's current contract: locale validation asks Symfony Intl whether the identifier is known. It is nevertheless noisy for applications that deliberately define aliases or variants.

**Recommendation:** Treat configurable locale aliases as an optional future feature. A mapping such as `de_informal: de_DE` could allow plural and locale validation to use the canonical target while preserving the application's lookup key. Until then, BookStack can disable invalid-locale diagnostics if it wants to keep this identifier.

### BS-EXT-05: `invalidChoice.missingCase` may over-approximate integer inputs

**Classification:** Requires focused reproduction<br>
**Impact:** Medium confidence<br>
**Area:** `src/CallRule/InvalidChoiceRule.php`

Seven `invalidChoice.missingCase` diagnostics remained. At least some appear to result from PHPStan representing a count as unrestricted `int` while the application domain guarantees a non-negative count. If the rule checks negative integers as possible values, it can report a missing case that is unreachable at runtime.

**Recommendation:** Preserve these examples before changing the rule. Determine whether PHPStan can infer a non-negative integer from the source or whether the rule needs a deliberately bounded policy. Do not suppress all missing-case findings: explicit choice ranges can still contain genuine gaps.

### BS-EXT-06: Unused replacement results need locale-preserving output

**Classification:** Triage limitation<br>
**Impact:** Medium<br>
**Area:** Replacement diagnostics under BladeStan

The full Blade pass reported 51 unused replacements. This category likely contains a mixture of:

- real placeholder drift in one locale;
- callers that intentionally pass a superset of replacement values; and
- duplicate reports for the same call across several localized values.

Because BladeStan wrapping drops the extension's locale/key/value metadata, the full set could not be classified reliably from JSON output alone.

**Recommendation:** Resolve BS-EXT-03 before using the count as a quality gate. Then group unused-replacement results by call site, key, and differing locale values so one faulty locale does not look like many unrelated caller defects.

## High-confidence BookStack translation findings

These findings describe the tested BookStack revision. They have not been submitted upstream as part of this work.

### BS-APP-01: Indonesian placeholder contains an unexpected space

**File:** `lang/id/entities.php`<br>
**Key:** `books_delete_explain`<br>
**Template:** `resources/views/books/delete.blade.php:19`

The Indonesian value contains `: bookName`, with a space after the colon, while the caller supplies `bookName` and Laravel placeholders use the contiguous form `:bookName`.

The focused `BookController` run exposed this as an unused replacement. This is a high-confidence typo because the supplied replacement cannot match the localized string.

**Likely fix:** Change `: bookName` to `:bookName`.

### BS-APP-02: Portuguese translation is empty

**File:** `lang/pt/entities.php:174`<br>
**Key:** `books_sort_auto_sort`<br>
**Use:** `resources/views/books/sort.blade.php:33`

The Portuguese translation is an empty string:

```php
'books_sort_auto_sort' => '',
```

The extension discards the empty value and reports the key as missing for locale `pt`. Because the key is used in a reachable view, this is not merely dead translation data.

**Likely fix:** Supply a Portuguese translation. Whether empty strings should be treated as missing is a defensible package policy and was useful in this case.

### BS-APP-03: Slovak choice ranges use invalid list syntax

**File:** `lang/sk/entities.php`<br>
**Examples:** `x_books`, `x_chapters`, `shelves_copy_permission_success`

Several Slovak strings use a condition shaped like:

```text
[2,3,4]
```

Laravel's choice range parser treats a bracketed comma expression as a two-ended range. Splitting the example produces a lower bound of `2` and an upper value of `3,4`, which is not numeric. The extension emitted two reachable `invalidChoice.nonNumeric` diagnostics in the Blade pass.

**Likely fix:** If the intent is the inclusive range two through four, use `[2,4]`. Each affected string should be checked against the intended Slovak grammar before making an upstream change.

### BS-APP-04: Icelandic choice string likely lacks a delimiter

**File:** `lang/is/entities.php`<br>
**Key:** `x_books`

The value is:

```text
:count Bók:count Bækur
```

It appears to concatenate singular and plural forms without the `|` delimiter expected by Laravel choice strings.

**Likely fix:** Insert the intended delimiter and verify the Icelandic forms. This is high confidence as a formatting defect, although the exact corrected wording should be reviewed by an Icelandic speaker.

## Interpretation of the diagnostic counts

The raw count of 96 Blade translation diagnostics should not be read as 96 BookStack defects.

| Category | Interpretation |
| --- | --- |
| 35 malformed choices | Mostly extension false positives from valid one-form and three-form locale behavior, with some genuine malformed strings mixed in |
| 7 missing choice cases | Mixed confidence; some may be caused by an overly broad `int` domain |
| 2 non-numeric choice conditions | High-confidence Slovak syntax defects |
| 51 unused replacements | Mixed; metadata loss prevents efficient locale-by-locale triage |
| 1 missing translation | High-confidence empty Portuguese translation |

Likewise, the 54 application-only diagnostics do not indicate 54 unrelated problems. Fifty-three are the same empty-array loader defect repeated for every locale, and one is the deliberate `de_informal` application locale.

## Performance observations

The extension increased BookStack's application-only analysis from roughly six seconds to roughly seven seconds. Enabling `fuzzySearch` did not produce a measurable difference at this resolution: both fuzzy-disabled and fuzzy-enabled passes were around seven seconds.

The full pass with Blade compilation completed in roughly twelve seconds. Its additional cost includes BladeStan compilation and 1,202 nested diagnostics, so it is not a clean measurement of this extension alone.

These measurements do not support the earlier concern that fuzzy search is too slow for practical use on this corpus. They also are not a formal benchmark: each configuration was run only enough to establish order of magnitude, without warm/cold cache controls or repeated statistical sampling.

## BladeStan and Larastan compatibility conclusion

Nothing in this experiment indicates that BladeStan 0.11.7 is incompatible with Larastan 3.10.0. Both were active in the successful final run.

The setup failure was application-specific:

1. BookStack shares its application instance with views.
2. BladeStan compiles that shared value into a container resolution of the concrete application class.
3. BookStack's container did not map that concrete class back to the existing instance.
4. Laravel constructed and globally installed a new, unbooted application.
5. Binding the concrete class to the existing instance prevented the second construction.

This workaround belongs in a BookStack-specific PHPStan bootstrap or a more general BookStack container fix. It should not be added to this extension.

## Limitations

- The experiment analyzed BookStack source statically; it did not boot a browser session, run database-backed application tests, or verify rendered translations at runtime.
- Blade coverage is limited to templates reachable through calls that BladeStan can resolve statically. Dynamically selected views may be absent.
- Installing BladeStan updated BookStack dependencies, so the final pass was not against the exact upstream lock state.
- The temporary bootstrap binding changes container behavior during PHPStan analysis and may conceal other BookStack bootstrap assumptions.
- BladeStan's error wrapping prevented complete locale-specific classification of unused replacements and some choice errors.
- Timing numbers are coarse observations from a single environment, not performance guarantees.
- The run did not enable unused-translation-string reporting, so it says nothing about orphaned keys in BookStack's 689 translation files.
- Only one BookStack release and one PHP/Laravel dependency resolution were tested.

## Recommended work order

### 1. Fix empty-array loading

This is small, clearly incorrect, and responsible for the entire 53-error loader flood. Add focused unit coverage and repeat the BookStack application-only run with loader diagnostics enabled.

**Acceptance criterion:** BookStack's conventional empty `validation.attributes` arrays produce no diagnostics, while unsupported non-string leaves still do.

### 2. Align choice analysis with Laravel

Build tests directly from `Illuminate\Translation\MessageSelector` behavior, including locale-specific plural indexes. Preserve the genuine Slovak and Icelandic cases as negative fixtures.

**Acceptance criterion:** Valid one-, two-, and three-form BookStack strings are accepted; malformed conditions and missing delimiters remain detectable.

### 3. Improve Blade diagnostic attribution

Create a minimal BladeStan integration fixture before changing production code. Determine what metadata survives nested analysis and whether a supported interface exists for preserving both template location and this extension's structured fields.

**Acceptance criterion:** A Blade translation error identifies the `.blade.php` file and line and retains enough key/locale data to distinguish one bad locale from a caller-wide problem.

### 4. Re-evaluate replacement findings

After metadata is preserved, rerun the 51 unused-replacement results and classify them into caller supersets, single-locale drift, and actual dead arguments.

### 5. Consider locale aliases

Design only if real applications beyond BookStack need it. Any alias feature should define how lookup, Symfony locale validation, and plural selection interact.

### 6. Add an optional real-application canary

Once the high-noise false positives are fixed, automate a pinned BookStack run outside the normal fast unit suite. A script or manually dispatched workflow is preferable initially because it depends on an external repository and a BookStack-specific bootstrap adjustment.

The canary should:

- pin the BookStack tag or commit;
- install this extension from the checked-out source;
- apply the minimal concrete-application binding in the analysis bootstrap;
- run the application-only analysis and a BladeStan analysis;
- filter or baseline unrelated BookStack/BladeStan diagnostics explicitly;
- assert a small expected set of extension identifiers rather than merely requiring exit code zero;
- record dependency versions and fail clearly when upstream dependency resolution changes.

## Proposed regression matrix

| Scenario | Unit | End-to-end fixture | BookStack canary |
| --- | :---: | :---: | :---: |
| Empty translation groups | yes | yes | yes |
| Invalid non-string leaves | yes | yes | optional |
| One-form choice locale | yes | yes | yes |
| Two-form choice locale | yes | yes | yes |
| Three-form choice locale | yes | yes | yes |
| Exact and range conditions | yes | yes | yes |
| Invalid range-list syntax | yes | yes | yes |
| Blade template source attribution | no | yes | yes |
| Locale/key/value metadata preservation | no | yes | yes |
| Application locale alias | yes, if implemented | yes | BookStack `de_informal` |
| Fuzzy-search timing guard | no | optional benchmark | observational |

## Reproduction outline

The original temporary checkout no longer exists, but the investigation can be reproduced without relying on it:

1. Check out BookStack `v26.05.3`.
2. Run its stock PHPStan configuration and confirm the clean baseline.
3. Add this repository as a non-symlinked Composer path repository and require the extension from `dev-develop` or a pinned commit.
4. Configure `langPath: lang`, `baseLocale: en`, and the desired fuzzy-search setting.
5. Run `app/` analysis once with default diagnostics and once with only `translationLoaderErrors` disabled.
6. Require BladeStan 0.11.7 with dependency updates.
7. In BookStack's analysis bootstrap, bind the existing application under its concrete class:

   ```php
   $app = app();
   $app->instance($app::class, $app);
   ```

8. Run the Blade-enabled analysis in JSON format.
9. Classify diagnostics whose identifier starts with `lostInTranslation.` separately from other compiled-template findings.

Exact Composer operations should be performed in an isolated clone because adding BladeStan changes the lock file and dependency graph.

## Final assessment

The integration was successful in the sense that the extension installed, ran quickly, analyzed real application and Blade translation calls, and found credible defects. It also showed that two core diagnostics need correctness work before the output is suitable as a CI gate on a multilingual Laravel application.

BookStack should remain an external canary rather than a required test dependency for now. After empty arrays and Laravel-compatible choice parsing are fixed, repeating this run will provide a much clearer signal and a practical basis for deciding whether to automate it.
