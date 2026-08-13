# Mutation Testing

[Infection](https://infection.github.io/guide/) measures whether the PHPUnit
suite detects small behavioral changes to covered source code. It complements
line coverage; it does not replace focused regression tests.

## Tool isolation

The package and its default development shell support PHP 8.1, while the
pinned Infection release requires a newer PHP version. Infection is therefore
kept out of `composer.json` and `composer.lock` and is provided by a dedicated
PHP 8.4 Nix shell with PCOV enabled:

```console
nix develop .#mutation
```

The shell pins Infection 0.34.2 by SHA-256. The mutation CI job downloads the
same PHAR and verifies the same checksum before adding it to `PATH`.

## Running Infection

Run the focused campaign over the translation loader, call rules, helper, and
utilities while developing:

```console
composer infection:core
```

Run the complete campaign over `src` before changing the mutation baseline:

```console
composer infection
```

Reports are written to `infection.log` and `infection-summary.log`. Both are
ignored by Git and uploaded by CI even if the campaign fails.

## Baseline policy

CI enforces both overall and covered-code mutation scores of 80. The overall
score prevents a loss of mutation coverage from being hidden by a stable score
on the code that remains covered. Change either threshold only after reviewing
a complete campaign under the pinned mutation shell. When a score changes:

1. Add assertions for escaped mutants that change documented behavior.
2. Ignore or document behaviorally equivalent mutants instead of coupling
   tests to implementation details.
3. Keep the threshold below a stable verified result, then raise it
   deliberately as tests improve.

Infection runs PHPUnit tests in the mutation process. The separate end-to-end
suite launches PHPStan as a child process and should not be treated as mutation
coverage because that process may not load the active mutant.

## Reviewed baseline

The complete PHP 8.4 campaign on 2026-08-12 generated 1,013 mutants. PHPUnit
killed 836 and 177 escaped, for 100% mutation code coverage and a covered-code
MSI of 82.53%. With 100% mutation code coverage, the overall MSI is also
82.53%. Both 80-point gates leave 2.53 percentage points of margin. No mutants
are hidden by source exclusions or Infection ignore rules.

The review added focused assertions for these observable contracts:

- Blade diagnostics from separate nested analyses accumulate, preserve order,
  and map to the nearest preceding nonempty template marker;
- Blade's outer rule rebuilds every collected diagnostic;
- JSON and PHP loaders preserve flattened translations, source lines, and
  invalid-value diagnostics;
- choice parsing distinguishes malformed conditions from ordinary text,
  reports multiple bad conditions, accepts multiline text, and optionally
  reports incomplete locale-specific plural forms;
- replacement validation checks every locale and applies Unicode-aware title
  casing;
- namespaced and mixed-case translation helper calls follow PHP function
  resolution while genuine namespaced overrides remain outside the helper contract;
- memoization caches null and non-null searches, invalidates on mutation, and
  remains enabled by default; and
- formatter exit codes, application locale detection, exception chaining, and
  boundary escaping remain observable.

The remaining 177 mutants were classified by component. A row accounts for
every survivor; "mixed" means the group contains both equivalent mutations and
valid edge behavior whose additional tests are lower priority than the current
gate.

| Component | Survivors | Classification and disposition |
| --- | ---: | --- |
| Translation discovery and loaders | 71 | Mixed parser, path, flattening, and defensive-boundary variants. Retain for future focused loader work; do not weaken loader assertions or ignore the whole component. |
| Call parsing and diagnostic rules | 55 | Mixed PHPStan type relationships, plural-policy table branches, fallback values, and multi-result control flow. Prioritize regressions tied to an observed application diagnostic. Structurally one-item return mutations are equivalent where a rule can emit at most one error. |
| Fuzzy implementations | 23 | Mostly alternate pruning, tie, and internal-index behavior. The `NaiveFuzzyStringSet` boolean-value mutations are equivalent because only its keys are read. Test externally visible suggestions; do not couple tests to the optional index algorithm. |
| JSON error formatter | 16 | Output aggregation and JSON-option variants. Exit status and pretty defaults are covered; add exact-output cases when a consumer requires a currently unasserted encoding detail. |
| Blade marker bounds | 6 | Equivalent for Bladestan's positive compiled lines and marker-before-call layout. The valid boundary contract is covered, so these are documented rather than ignored by broad line-number mutator rules. |
| Unused-string collectors | 3 | Two empty-constant-key early returns are equivalent because the following loops enqueue nothing; one fake-collector forwarding call remains an integration seam. |
| Locale and application utilities | 3 | The regex `D` flag is redundant with the terminal anchor here; the other two differ only by autoloading a class that these detection helpers intentionally require to be loaded already. |

The first follow-up priority is an observed behavior gap in the translation
loader or call-analysis groups. Equivalent mutations remain visible in the log
so a future implementation change cannot inherit an overly broad ignore.
