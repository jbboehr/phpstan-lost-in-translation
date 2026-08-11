# Repository guidance

These instructions apply to the entire repository.

## Project scope

This package is a PHPStan extension for Laravel translation files. It supports PHP 8.1 through 8.5, PHPStan 1.12 and
2.x, and the compatible Laravel 9 through 13 combinations encoded in `.github/workflows/ci.yml`.

Preserve compatibility across that declared matrix. Do not use a newer PHP, PHPUnit, PHPStan, Laravel, or PHP-Parser API
without checking the oldest supported combination.

## Normal verification

Install dependencies and run the ordinary review gate with:

```shell
composer install
composer check
```

Use `composer check:full` for changes affecting packaging, extension registration, benchmarks, runtime dependency
boundaries, or end-to-end diagnostics. Run `nix flake check -L` after changing Nix or repository scaffolding.

During focused iteration, run the narrowest relevant command first. The shared Composer entry points are:

- `composer phpcs` for coding standards;
- `composer analyse` for PHPStan;
- `composer test` for PHPUnit;
- `composer runtime-smoke` for a production-dependency extension load;
- `composer e2e` for expected diagnostics; and
- `composer infection` for optional mutation testing.

## Architecture boundaries

- `extension.neon` is the package entry point and service-registration contract.
- `src/TranslationLoader/` discovers and loads supported Laravel translation layouts. Preserve deterministic locale,
  namespace, file, and line metadata.
- `src/CallRule/` validates individual translation calls. Keep argument binding, locale selection, and replacement
  normalization shared rather than reimplemented per rule.
- `src/Rule/` integrates collected loader and call diagnostics with PHPStan.
- `src/Blade/` bridges diagnostics out of Bladestan's nested analysis. Its collector queue is process-local analysis
  plumbing, not Laravel's queue system.
- `src/Fuzzy/` contains swappable suggestion implementations. Empty candidate sets must remain safe.
- `tests/data/`, `tests/lang*/`, and `e2e/` contain intentionally valid and invalid fixtures. Do not “fix” fixture
  diagnostics without updating the corresponding assertions or expected output.

Behavior changes require focused regression coverage. Prefer asserting diagnostic identifiers, metadata, tips, paths,
and lines where those fields are part of the behavior being changed.

## Dependencies and packaging

Runtime code may use only packages and PHP extensions declared under `require`. Development-only integrations belong
under `require-dev` and must not be needed to load the installed extension.

Keep `composer.lock` synchronized with dependency-constraint changes. The lowest-dependency CI job is authoritative for
the lower bounds; the Laravel matrix covers supported framework combinations.

The Composer archive policy intentionally retains `src/`, `extension.neon`, README and licensing material while
excluding development-only fixtures and tooling. Revisit the archive exclusions when adding a new runtime asset.

## Documentation and planning

The README is the public usage reference. Keep configuration and diagnostic examples consistent with the shipped
extension and its fixtures.

Engineering reports and remediation plans live under `docs/`. Update planning status when completing a recorded item,
but do not rewrite historical findings to pretend they were never present.

`tmp.md` is transient review input. Do not stage or commit it unless the user explicitly requests that exact file.

## Generated and local files

Do not edit `vendor/`, coverage output, Infection logs, PHPStan logs, PHPUnit caches, or agent-badge state as source.
`.github/agent-badge/config.json` is tracked; its state, cache, and logs are intentionally ignored.

Before every commit, inspect the staged path list. Never stage or commit anything under `secrets/` without the user's
explicit confirmation for that specific operation.

## Licensing

The project license is `AGPL-3.0-only WITH romic-exception`. New files with project source headers must use that SPDX
expression. Contribution terms, the optional CLA route, and the Project Steward are documented in `CONTRIBUTING.md` and
`docs/`.
