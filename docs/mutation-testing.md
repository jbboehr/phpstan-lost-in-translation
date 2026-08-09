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

The shell pins Infection 0.34.2 by SHA-256. The mutation CI job installs the
same version with the same checksum.

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

The initial CI campaign intentionally has no minimum mutation score. Its job is
to prove the tooling remains functional and to expose escaped mutants for
triage. After the initial report has been reviewed:

1. Add assertions for escaped mutants that change documented behavior.
2. Ignore or document behaviorally equivalent mutants instead of coupling
   tests to implementation details.
3. Establish a covered-code mutation score threshold below the stable
   baseline, then raise it deliberately as tests improve.

Infection runs PHPUnit tests in the mutation process. The separate end-to-end
suite launches PHPStan as a child process and should not be treated as mutation
coverage because that process may not load the active mutant.
