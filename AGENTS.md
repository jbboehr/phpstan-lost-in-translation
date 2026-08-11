# Repository guidance

These instructions apply to the entire repository.

## Project scope

This package is a PHPStan extension for Laravel translation files. It supports PHP 8.1 through 8.5, PHPStan 1.12 and
2.x, and the compatible Laravel 9 through 13 combinations encoded in `.github/workflows/ci.yml`.

Preserve compatibility across that declared matrix. Do not use a newer PHP, PHPUnit, PHPStan, Laravel, or PHP-Parser API
without checking the oldest supported combination.

## Doctrine of the Second Sun

This repository adopts Doctrine of the Second Sun through the Composer development dependency at
`vendor/jbboehr/doctrine-of-the-second-sun/`. The source repository's `composer.lock` pins the reviewed revision.

The adopted authorities are:

- `DOCTRINE-STYLE-GUIDE.md`, `DOCTRINE-CODING-GUIDE.md`, and `DOCTRINE-GENERATION-GUIDE.md` for logia and their safe
  generation, review, and insertion;
- `DOCTRINE-GOLD-EXEMPLARS.md` as a nonnormative quality ceiling;
- `MEASURE-OF-WORDS.md` for technical prose;
- `RUINENWERT.md` for technical architecture, invariants, conformance, compatibility, reproducibility, preservation,
  and replacement boundaries, but not for succession, stewardship, or governance; and
- `CODE_OF_SOVEREIGNTY.md` as the repository governance policy.

This file is authoritative for repository-specific scope, placement, citation allocation, and verification. The
installed documents govern their subjects within that scope. The image guide and browser integration are not adopted.

### Governance

The Code of Sovereignty is explicitly adopted. In the original repository, the Sovereign is `jbboehr`, acting through
the Project Steward identified in `docs/STEWARD.md`. A fork has its own Sovereign as defined by the Code. This
governance policy does not alter the project license, contribution terms, contracts, or obligations imposed by law.

### Logion scope and form

Doctrine applies prospectively to named classes, interfaces, traits, enums, methods, and functions newly introduced
under `src/`. It does not apply to preexisting declarations merely because they are edited, or to anonymous
declarations, properties, constants, tests, fixtures, stubs, `e2e/`, generated files, dependencies, or vendored code.
Backfill requires an explicit repository-wide or otherwise precisely scoped doctrine pass.

Each applicable declaration must have exactly one PHPDoc tag in this form:

```text
@logion [BOOK C:V] passage
```

Allowed book codes are `OSD`, `RAS`, `AWC`, and `SFA`; chapter and verse are positive integers. Allocate a previously
unused reference only after literary selection, verify uniqueness across applicable declarations under `src/`, preserve
the reference when a declaration moves or is renamed, and never intentionally reuse a retired reference. Keep accurate
technical PHPDoc and static-analysis annotations alongside the tag.

Before generating or editing a logion, read the installed style, coding, generation, and exemplar documents completely.
Fix an opaque declaration mapping before generation. When isolated roles are available, the writer must not see the
declaration, the literary reviewer must not see the code or writer rationale, and the code-aware review is a reject-only
implementation-leakage check. Otherwise use the generation guide's portable fallback and disclose the reduced
isolation. Do not select or rewrite a passage because it resembles the declaration's name or behavior.

Doctrine-only work must not change runtime behavior, signatures, technical documentation, dependency versions, or test
expectations. Preserve existing logia and citations unless the task explicitly authorizes changing them.

The bundled PHPStan enforcement adapter is not enabled because it supports PHPStan 2.x while this project supports
PHPStan 1.12. Do not include it in the shared PHPStan configuration. Revisit enforcement in a dedicated PHPStan 2 job or
after PHPStan 1 support is dropped.

### Technical preservation

Apply Ruinenwert during structural work: keep the translation-analysis semantics separable from Laravel, PHPStan,
Larastan, and Bladestan adapters; prefer stable diagnostic identifiers over exact prose; record important invariants and
their reasons; test public inputs and outputs at compatibility boundaries; keep dependency claims within the tested
matrix; and preserve conventional local entry points such as the Composer checks below. Do not introduce abstractions,
packages, documents, or generated artifacts solely to imitate the guide's examples.

Apply the Measure of Words to technical documentation, comments, plans, reviews, commit messages, and contribution text.
Lead with the result, retain necessary constraints and rationale, and remove prose that adds no fact, decision, reason,
condition, action, risk, or required context. It does not govern logia or other literary text.

Treat updates to `jbboehr/doctrine-of-the-second-sun` as policy changes: review the upstream document diff, update this
local policy when necessary, and run the repository's normal checks. For any doctrine-bearing source change, also verify
scope, tag form, citation uniqueness, preservation of existing references, literary review, and absence of
implementation leakage.

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
