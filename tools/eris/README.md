# Property tests

This isolated Composer project runs Eris 1.1 with PHPUnit 10 on PHP 8.1. No supported Eris release spans the main
project's PHPUnit 9 through 11 matrix, so these dependencies must remain outside the root development requirements.

From the repository root, install and run the suite with:

```shell
composer eris
```

The default seed is `20260811`. Override it to explore or replay another generated sequence without editing tracked
configuration:

```shell
ERIS_SEED=123456789 composer eris:test
```

Focused example tests remain the primary regression suite. These properties supplement them by checking invariants over
many locale spellings and translation-key combinations, memoized fuzzy-search operation sequences, generated JSON
catalogues, and arbitrary diagnostic bytes.

Every property invokes package code in the PHPUnit process. The suite does not
delegate assertions to the PHPStan end-to-end subprocess. Infection does not
load this isolated Composer project, so promote every discovered counterexample
to a focused root PHPUnit regression before relying on a changed mutation score.
