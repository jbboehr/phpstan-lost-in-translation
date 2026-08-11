# BookStack canary

This optional integration check clones BookStack `v26.05.3` and verifies that the tag still resolves to commit
`e1cd3229966d939a75a74a2224ff0643d8af337b`. It builds the current extension checkout with `composer archive`, extracts
that artifact, installs it as a copied Composer path package, and runs three analyses:

1. BookStack's stock application analysis must remain clean.
2. Application-only translation analysis must report only BookStack's known `de_informal` locale alias.
3. Blade analysis must retain selected translation identifiers and tips while the known empty-array and
   malformed-choice regressions remain absent.

BladeStan's own diagnostics and ordinary diagnostics from compiled templates are still generated. The assertion layer
filters them from this extension's canary contract instead of adding broad PHPStan ignores. Curated signatures provide
the behavioral contract; a broad range of 40 through 100 extension diagnostics catches major disappearance or flooding
without freezing the complete mixed-confidence histogram, currently 62.

The check requires network access, Git, Composer, `tar`, and PHP 8.4. Run it from the repository root with:

```shell
nix develop .#php84 --command composer bookstack:canary
```

The resolved boundary is asserted as PHPStan 2.2.6, Larastan 3.10.0, Laravel 12.64.0, BladeStan 0.11.7, and Livewire
4.4.0. Minimal-change resolution preserves BookStack's locked core versions while adding the explicitly pinned Blade
dependencies. The temporary checkout also updates development-only PHP_CodeSniffer from BookStack's vulnerable 4.0.1
lock to fixed version 4.0.2; the canary does not execute PHP_CodeSniffer.

Set `BOOKSTACK_CANARY_KEEP_TEMP=1` to retain the isolated checkout after the run. The command is intentionally absent
from `composer check` and `composer check:full`; `.github/workflows/bookstack.yml` exposes it as a manual external
canary.

When updating the pin, review the BookStack diff and dependency resolution, refresh the bootstrap patch and curated
diagnostic signatures, and update `docs/development/bookstack-integration-report.md`. Do not loosen an assertion merely
because upstream output drifted.
