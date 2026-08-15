# Repository guidance

These instructions apply to the entire repository.

## Project scope

This package is a PHPStan extension for Laravel translation files. It supports PHP 8.1 through 8.5, PHPStan 1.12 and
2.x, and the compatible Laravel 9 through 13 combinations encoded in `nix/validation.nix` and its Composer locks.

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

The Codex writer and reviewer adapters under `.codex/agents/` are reviewed, repository-owned copies of the adapters in
`vendor/jbboehr/doctrine-of-the-second-sun/integrations/codex/agents/`. Keep them byte-for-byte synchronized with the
reviewed Composer pin. Installing or updating the dependency does not register or update the local copies automatically.

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
local policy and committed Codex adapters when necessary, and run the repository's normal checks. For any
doctrine-bearing source change, also verify scope, tag form, citation uniqueness, preservation of existing references,
literary review, and absence of implementation leakage.

## Normal verification

Use the ordinary mutable Composer installation for focused iteration:

```shell
composer install
composer check
```

The authoritative routine gate is:

```shell
nix flake check --keep-going -L
```

It includes the supported compatibility matrix, static checks, packaging and runtime checks, and isolated Eris and
Akashi consumers. It uses Nix-managed Composer dependencies rather than the checkout's `vendor/`. Mutation testing is
an explicit CI target under `packages`, not a flake check; run it with `nix build .#mutation -L`.

Use `composer check:full` for focused iteration on packaging, extension registration, benchmarks, runtime dependency
boundaries, or end-to-end diagnostics. Run the authoritative Nix gate after changing Nix or repository scaffolding.

Run `composer eris` for the isolated property suite after changing locale canonicalization or translation-key parsing.
The command validates and installs its locked PHPUnit 10 and Eris dependencies under `tools/eris/` before running the
suite. Set `ERIS_SEED` to override the tracked default when exploring or replaying generated inputs.

Run `composer docs:check` from the PHP 8.2 `documentation` shell after changing the README, the mdBook user guide, marked
translation-call examples, or their expected diagnostics. The command builds the book, validates its generated links,
and runs an isolated Akashi harness with PHPStan 2, PHPUnit 11, and Laravel 12 independently of the root compatibility
matrix. These checks have separate exhaustive Nix CI entries rather than running inside the PHP 8.1
`composer check:full` gate.

During focused iteration, run the narrowest relevant command first. The shared Composer entry points are:

- `composer phpcs` for coding standards;
- `composer analyse` for PHPStan;
- `composer test` for PHPUnit;
- `composer runtime-smoke` for a production-dependency extension load;
- `composer package:check` for the built archive and an isolated PHPStan 1.12 consumer;
- `composer e2e` for expected diagnostics;
- `composer eris` for isolated locale and translation-key properties;
- `composer docs:check` for the mdBook build, generated links, and marked public translation-call examples;
- `composer infection` for optional mutation testing; and
- `composer bookstack:canary` from the PHP 8.4 shell for the optional pinned real-application integration check.

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

The Eris suite is a separate Composer project because no supported Eris release spans the root PHPUnit 9 through 11
matrix. Keep its dependencies and lock under `tools/eris/`; do not add Eris to the root development requirements.

The Akashi documentation suite is a separate Composer project so its fixed PHPStan 2, PHPUnit 11, and Laravel 12
verification stack does not alter the root PHP 8.1 and PHPStan 1.12 dependency resolution. Keep its dependencies and lock under
`tools/akashi/` while the root compatibility matrix spans both PHPStan major versions. Akashi intentionally tracks
`dev-master`; the isolated lock pins the reviewed revision, and upstream API changes must be reviewed when updating it.

The BookStack canary is an external, networked PHP 8.4 check, not part of the normal local or pull-request gate. Keep
BookStack, BladeStan, Livewire, and core analysis versions pinned in `tools/bookstack/`; preserve its non-symlinked path
installation from an extracted Composer archive, explicit filtering of non-extension BladeStan diagnostics, curated
identifier-and-tip assertions, and broad diagnostic-count guard.

Keep `composer.lock` synchronized with dependency-constraint changes. The lowest-dependency Nix checks are authoritative
for the lower bounds; the Laravel matrix covers supported framework combinations.

The Composer archive policy intentionally retains `src/`, `extension.neon`, README and licensing material while
excluding development-only fixtures and tooling. Revisit the archive exclusions when adding a new runtime asset.
`composer package:check` enforces that allowlist, installs the resulting artifact without a source symlink, verifies
automatic extension discovery, and exercises both standard and custom-formatted diagnostics.
When changing the shipped file set, update both `composer.json` archive exclusions and the required or allowed paths in
`inspectPackageArchive()` under `tools/check-package.php`.

## Documentation and planning

The README is the package landing page, and `docs/usage/` is the mdBook user guide. Keep configuration and diagnostic
examples on both public surfaces consistent with the shipped extension and its fixtures.

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
