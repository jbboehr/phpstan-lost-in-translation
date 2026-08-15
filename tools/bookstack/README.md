# BookStack canary

This optional integration check clones BookStack `v26.05.3` and verifies that the tag still resolves to commit
`e1cd3229966d939a75a74a2224ff0643d8af337b`. It builds the current extension checkout with `composer archive`, extracts
that artifact, installs it as a copied Composer path package, and runs three analyses:

1. BookStack's stock application analysis must remain clean.
2. Application-only translation analysis must retain the expected identifier set and curated findings when
   `de_informal` is configured as an alias of `de_DE` and opt-in plural-form completeness is enabled.
3. Blade analysis must reproduce the application diagnostics and retain the selected Blade-specific identifiers and
   tips, including plural-form findings, while the known empty-array and malformed-choice regressions remain absent.

Namespaced global-helper resolution currently exposes 179 application diagnostics: 137 unused replacements, 21
missing-base-locale keys, and 21 missing-locale keys. The assertion uses broad total and replacement-count guards,
curates the likely missing `passwords.throttled` and `entities.comment_deleted` keys, and records 38 missing-key
diagnostics in `SortRuleOperation.php` as a known PHPStan inference artifact. PHPStan applies both `substr()` branches
to an insufficiently narrowed union of enum values there, producing 19 impossible keys under each missing-key
identifier. The canary asserts this noise explicitly so either a regression or an upstream inference improvement
requires review.

BladeStan's own diagnostics and ordinary diagnostics from compiled templates are still generated. The assertion layer
filters them from this extension's canary contract instead of adding broad PHPStan ignores. The full Blade pass
currently contains the same 179 application diagnostics plus 144 Blade-specific diagnostics. Exact diagnostic
fingerprints from the application pass are subtracted before evaluating the Blade contract, so disappearance or drift
between the two passes fails independently. The remainder contains 55 non-plural extension diagnostics guarded by a
40-through-100 range and 89 opt-in `invalidChoice.missingPluralForm` diagnostics guarded by a 70-through-110 range.
Curated plural signatures cover the known Icelandic missing delimiter and prove that the configured
`de_informal: de_DE` alias supplies the plural policy.

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
