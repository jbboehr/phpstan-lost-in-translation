# Benchmarking

The benchmark suite measures the extension's in-process hot paths without treating PHPStan startup, Composer, or an
application subprocess as project work. It is diagnostic evidence, not a cross-machine performance score.

## Coverage

| Area | Fixture | Cold/warm distinction |
| --- | --- | --- |
| Fuzzy suggestions | 1,000 and 10,000 candidate keys | uncached lookup and memoized hit |
| Missing-key rule | 10,000 loaded catalogue entries | initial fuzzy lookup and memoized repeat |
| JSON catalogue loading | three locales with 1,000 entries each | scan and parse |
| Nested PHP catalogue loading | 1,000 array parents with nested string leaves | scan, parse, validate, and flatten |
| Unused-key analysis | 3,000 catalogue entries and 500 wildcard uses | preloaded catalogue |

Assertions inside each subject confirm that the measured call still returns the intended result. The suite does not
set timing thresholds: wall time depends on scheduling and hardware counters depend on the host PMU. Regressions should
be evaluated with repeated runs on the same host, PHP version, counter set, and revision configuration.

## Surfaces

- `composer benchmark:smoke` runs one iteration and revolution as the portable execution check used by the full
  Composer gate and the PHP 8.1 Nix check.
- `composer benchmark` produces the normal repeated wall-clock report.
- `composer benchmark:perfidious` measures the software CPU clock from the x86-64 Linux benchmark shell.
- `composer benchmark:perfidious:hardware` adds instructions, cycles, cache misses, and branch misses when the host
  exposes them.
- `nix build .#benchmark-perfidious -L` checks the Perfidious integration explicitly on a compatible local host.

GitHub-hosted Actions runners do not expose usable Linux performance events. The generated CI matrix therefore runs
the portable `benchmark-smoke` check but omits the explicit Perfidious target.

Perfidious uses its isolated executor. The current long-lived executor reuses one performance-counter handle across
variants; on the validation host, the first interval counted normally while later intervals returned zero. Isolated
workers give each variant a fresh handle. The benchmark methods still call project code directly, so the counters do
not merely measure a parent process waiting for PHPStan or another application.

## Initial observation

On the implementation host under PHP 8.4, the repeated wall-clock run separated the formerly mixed cache states:

- memoized fuzzy hits were about 0.05--0.1 microseconds;
- cold fuzzy lookup was about 0.07--0.22 milliseconds for 1,000 candidates and 13--19 milliseconds for 10,000;
- a cold missing-key diagnostic over 10,000 entries was about 7.6 milliseconds, while a memoized repeat was about
  1.7 microseconds;
- loading 3,000 JSON catalogue entries was about 18 milliseconds; and
- unused-key analysis over that catalogue was about 1.3 milliseconds.

These values establish order of magnitude only. Retain raw output when using a later run to justify an optimization.
