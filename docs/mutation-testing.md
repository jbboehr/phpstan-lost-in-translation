# Mutation Testing

[Infection](https://infection.github.io/guide/) measures whether the PHPUnit
suite detects small behavioral changes to covered source code. It complements
line coverage; it does not replace focused regression tests.

## Tool isolation

The package and its default development shell support PHP 8.1, while the
pinned Infection release requires a newer PHP version. Infection is therefore
kept out of `composer.json` and `composer.lock` and is provided by Nix with PHP
8.4 and PCOV. Run the complete, CI-equivalent campaign explicitly with:

```console
nix build .#mutation -L
```

This target is not under `checks`, so `nix flake check` does not run mutation
testing. The exhaustive Nix GitHub Actions matrix adds the target explicitly.
Nix pins Infection 0.34.2 by SHA-256 and installs the same fixed Composer
closure used by the routine checks.

## Running Infection

For interactive investigation, enter the mutation shell:

```console
nix develop .#mutation
```

Run the focused campaign over the translation loader, call rules, helper, and
utilities:

```console
composer infection:core
```

Run the complete campaign over `src` before changing the mutation baseline:

```console
composer infection
```

Interactive reports are written to `infection.log` and
`infection-summary.log`, both ignored by Git. A successful Nix mutation build
retains both reports in its output. On failure, Infection's score and Nix's
original failure remain in the CI build log.

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

The isolated Eris suite calls package code in its own PHPUnit process and does
not use the end-to-end subprocess. It is nevertheless outside Infection's root
PHPUnit campaign because its dependency closure is intentionally separate.
When a property finds a counterexample, add a focused root PHPUnit regression;
that regression then participates in mutation testing.

## Reviewed baseline

The complete PHP 8.4 campaign on 2026-08-14 generated 1,065 mutants. PHPUnit
killed 886 and 179 escaped, for 100% mutation code coverage and a covered-code
MSI of 83.19%. With 100% mutation code coverage, the overall MSI is also
83.19%. Both 80-point gates leave 3.19 percentage points of margin. No mutants
are hidden by source exclusions or Infection ignore rules.

The review added focused assertions for these observable contracts:

- Blade diagnostics from separate nested analyses accumulate, preserve order,
  and map to the nearest preceding nonempty template marker;
- Blade's outer rule rebuilds every collected diagnostic;
- translation discovery separates root JSON, grouped PHP, and vendor-namespaced
  PHP catalogues; loaders preserve callable fuzzy keys, flattened translations,
  source lines, and invalid-value diagnostics;
- choice parsing distinguishes malformed conditions from ordinary text,
  reports multiple bad conditions, accepts multiline text, and optionally
  reports incomplete locale-specific plural forms; choice coverage also
  normalizes counted inputs without discarding other union members;
- replacement validation checks every locale and applies Unicode-aware title
  casing;
- namespaced and mixed-case translation helper calls follow PHP function
  resolution while genuine namespaced overrides remain outside the helper contract;
- memoization caches null and non-null searches, invalidates on mutation, and
  remains enabled by default;
- formatter exit codes, application locale detection, exception chaining, and
  boundary escaping remain observable; and
- numeric fuzzy candidates remain strings instead of becoming integer array
  keys, with a root regression promoted from the differential property suite.

The remaining 179 mutants were classified by component. A row accounts for
every survivor; "mixed" means the group contains both equivalent mutations and
valid edge behavior whose additional tests are lower priority than the current
gate.

| Component | Survivors | Classification and disposition |
| --- | ---: | --- |
| Translation discovery and loaders | 69 | Mixed parser, path, flattening, and defensive-boundary variants. Retain for future focused loader work; do not weaken loader assertions or ignore the whole component. |
| Call parsing and diagnostic rules | 59 | Mixed PHPStan type relationships, plural-policy table branches, fallback values, and multi-result control flow. Prioritize regressions tied to an observed application diagnostic. Structurally one-item return mutations are equivalent where a rule can emit at most one error. |
| Fuzzy implementations | 23 | Mostly alternate pruning, tie, and internal-index behavior. The `NaiveFuzzyStringSet` membership-map boolean mutations are equivalent because membership uses `isset()`. Test externally visible suggestions; do not couple tests to the optional index algorithm. |
| JSON error formatter | 16 | Output aggregation and JSON-option variants. Exit status and pretty defaults are covered; add exact-output cases when a consumer requires a currently unasserted encoding detail. |
| Blade marker bounds | 6 | Equivalent for Bladestan's positive compiled lines and marker-before-call layout. The valid boundary contract is covered, so these are documented rather than ignored by broad line-number mutator rules. |
| Unused-string collectors | 3 | Two empty-constant-key early returns are equivalent because the following loops enqueue nothing; one fake-collector forwarding call remains an integration seam. |
| Locale and application utilities | 3 | The regex `D` flag is redundant with the terminal anchor here; the other two differ only by autoloading a class that these detection helpers intentionally require to be loaded already. |

The first follow-up priority is an observed behavior gap in the translation
loader or call-analysis groups. Equivalent mutations remain visible in the log
so a future implementation change cannot inherit an overly broad ignore.
