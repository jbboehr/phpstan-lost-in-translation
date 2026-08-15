# First-release readiness

**Audited:** 2026-08-14 (America/Los_Angeles)<br>
**Updated:** 2026-08-15 after `v0.1.0` publication<br>
**Initial source:** `develop` at `50e3e2e` (`Add mdBook user guide`)<br>
**Release:** `v0.1.0` at `41d8aa4` (`Update first release readiness`)<br>
**Scope:** Package metadata, distributable archive, public documentation, dependency health, GitHub configuration,
Packagist state, and release validation

## Decision summary

The first stable Composer version is published. The signed annotated `v0.1.0` tag points to `41d8aa4`, the synchronized
`master` and `develop` tip at publication. Conventional CI, the exhaustive Nix matrix, and Pages pass at that commit;
the pinned BookStack canary, shipped archive, isolated PHPStan 1.12 consumer, and runtime dependency audit also passed
during release-candidate validation.

The initial audit found `master` 57 commits behind `develop`, obsolete Packagist metadata, and no hosted guide. The
default-branch cutover resolved those findings: Packagist now publishes current metadata for both development branches,
and Pages is live. Repository metadata and protection changes were authorized on 2026-08-15, but the available GitHub
token lacks repository-administration and workflow-write permissions. The release proceeded with an explicit waiver;
the selected values remain recorded under RR-04 as deferred repository housekeeping.

Packagist indexes the tag as `v0.1.0`, normalized to `0.1.0.0`, with the current source reference, dependencies,
description, and license. No GitHub Release object was created, by decision.

## Current state

| Area | Observation | Release effect |
| --- | --- | --- |
| Source | `master` and `develop` both resolved to `41d8aa4` at publication. | The tag points to the reviewed, synchronized branch tip. |
| Tags and releases | Signed annotated tag `v0.1.0` points to `41d8aa4`; no GitHub Release object exists. | The first stable Composer version is published without separate GitHub release notes. |
| Composer metadata | `composer.json` has the current description, PHP `^8.1`, PHPStan `^1.12 || ^2.0`, and `AGPL-3.0-only WITH romic-exception`. | Suitable for a pre-1.0 release. |
| Package archive | `composer package:check` found the expected 53-file allowlisted archive and passed an isolated PHPStan 1.12 consumer. | The distributable contents and extension discovery are ready. |
| Runtime dependencies | `composer audit --locked --no-dev` reports no advisories. | No known advisory affects the shipped dependency closure. |
| Development dependencies | The full lock audit reports Laravel advisories and abandoned `doctrine/annotations`. Laravel 10 is present only under `require-dev` to test a supported compatibility boundary. | Do not ship these dependencies. Track supported-framework advisories without hiding them or dropping compatibility silently. |
| Conventional CI | Run `31875010500` passed on `master` at `41d8aa4`. | Independent PHPUnit, PHPStan, php-cs-fixer, Composer validation, and runtime audit coverage is green. |
| Nix CI | Run `31875010484` passed on `master` at `41d8aa4`, including mutation and the PHP/Laravel matrix. | Reproducible compatibility, documentation, consumer, coverage, property, and mutation checks are green. |
| BookStack canary | Run `31873535591` passed at `1b0fb11` under `develop` before the identical commit became `master`. A branch-specific redispatch was rejected with HTTP 403. | The release source is validated; redispatch from `master` when workflow-write credentials are available if branch-labelled evidence is required. |
| User documentation | README and `docs/usage/` advertise the resolvable `^0.1` constraint. Pages run `31875010487` passed from `master`; the guide is live at `https://jbboehr.github.io/phpstan-lost-in-translation/`. | Hosted installation documentation matches the published version. |
| Packagist | `v0.1.0` resolves to `41d8aa4` with the current description, dependencies, and license. | Ordinary stable Composer installations now resolve without a development stability override. |
| GitHub metadata | The description remains the older missing-strings-only summary, no homepage is set, and `master` remains unprotected because the authorized update received HTTP 403. | RR-04 was waived for `v0.1.0`; apply the selected settings later with repository-administration credentials. |
| Changelog | `CHANGELOG.md` retains an empty `Unreleased` section whose comparison now begins at `v0.1.0`. | The first tag is the baseline; subsequent notable changes are recorded without reconstructing pre-tag history. |

## Release blockers

### RR-01 — Reconcile `develop` with the default branch

**Status:** Resolved. The authorized fast-forwards aligned `master` and `develop` through the released `41d8aa4` tip.

`master` is the source of Packagist's default development version and the trigger for documentation deployment. Tagging
the current `develop` commit without first updating `master` would leave the repository landing page and Packagist's
default branch on the obsolete package.

The initial 57-commit difference is retained in the audit evidence below. The cutover used the reviewed release
candidate without introducing a merge commit or an unvalidated branch tip.

### RR-02 — Choose the first version and first-tag changelog boundary

**Status:** Resolved for release preparation. The selected version is `0.1.0`, and the public installation constraint is
`^0.1`.

Keep the changelog skeleton empty through the first tag, matching the existing project decision. Treat that tag as the
baseline and record subsequent notable changes under `Unreleased`. If a historical initial-release summary is desired,
that is a separate policy decision rather than an automatic reconstruction of the pre-tag history.

### RR-03 — Validate the release candidate on `master`

**Status:** Resolved for source validation at `1b0fb11`.

The release candidate includes the installation, documentation, metadata, and BookStack-canary changes. Its required
gates passed as follows:

- conventional CI passed on `master` in run `31873777456`;
- the complete generated Nix matrix, including mutation, passed on `master` in run `31873777510`;
- `composer package:check` passed against the release-candidate package contents;
- the pinned BookStack canary passed at the exact release-candidate SHA in run `31873535591`; and
- Pages deployed successfully from `master` in run `31873777475`.

The BookStack run is labelled `develop` because it completed before the same SHA became `master`. The workflow and
canary do not branch on the ref name. A later dispatch from `master` was attempted for administrative completeness but
the available token received HTTP 403.

### RR-04 — Make release-critical repository settings explicit

**Status:** Waived for `v0.1.0`; retained as deferred repository housekeeping.

The selected policy preserves the current solo direct-push workflow while preventing destructive history changes:

- require linear history on `master`;
- enforce protection for administrators;
- disallow force-pushes and branch deletion;
- continue allowing ordinary direct pushes; and
- do not require pull-request approvals or duplicate the generated Nix matrix as required status-check contexts.

The selected repository description is `PHPStan extension for validating Laravel translation keys, replacements,
choices, locales, files, and Blade usage`. The selected homepage is
`https://jbboehr.github.io/phpstan-lost-in-translation/`.

Applying the description, homepage, protection, and branch-specific BookStack dispatch through the current token each
received HTTP 403. The settings remained unchanged when `v0.1.0` was published under explicit authorization. Apply them
later with repository-administration credentials; they are no longer tracked as first-release blockers.

## Not release blockers

- A dedicated release workflow was unnecessary for the first tag. The manually created signed annotated tag is the
  complete release artifact; no GitHub Release object was requested or created.
- The development-only Laravel advisories do not affect the shipped archive or runtime dependency closure. Compatibility
  locks must still be reviewed and refreshed where patched versions remain within the tested bounds.
- GitHub's simplified license detector may not display the Romic Exception. The shipped SPDX expression and license
  documents remain authoritative.
- The deferred PHP-Parser API cleanup should remain deferred while PHPStan 1.12 support requires the compatibility path.
- Surviving Infection mutations do not justify behavior changes without an observable specification or coverage gap.
- A `SECURITY.md` may be useful later, but its absence does not prevent a small pre-1.0 release.

## Ordered release slices

### Slice 1 — Readiness audit

**Status:** Complete in commit `731d0b6`.

This document records current evidence, blockers, decisions, and the release boundary without changing release
behavior.

### Slice 2 — Local release preparation

**Status:** Complete.

The README and user guide now install the `0.1` release line with `^0.1` while retaining and explaining the experimental
stability signal. Current planning text records the complete Akashi documentation scope and the closure of GitHub issue
#1. The pre-tag changelog remains empty. Documentation verification passes, and `composer package:check` confirms that
the 53-file archive includes the intended public documents and works in an isolated PHPStan 1.12 consumer.

These changes remain on `develop` for review. No tag or `master` update belongs to this slice.

### Slice 3 — Release-candidate validation

**Status:** Complete for the `develop` release candidate at `65ea7b0`.

The final local gates passed:

```shell
composer check:full
nix develop .#documentation --command composer docs:check
nix flake check --keep-going -L
composer package:check
```

The conventional workflow passed in run `31868405228`. The generated Nix workflow passed in run `31868405226`,
including the CI-only mutation target. A fresh `0.1.0` Composer archive contained the 53 approved files, current package
metadata and license, extension registration, public guide sources, and the `^0.1` installation command. The archive
excluded development tooling and `secrets/` and was removed after inspection. Repeat the remote gates after the
default-branch cutover in Slice 4.

### Slice 4 — Default-branch cutover

**Status:** Complete. The branches were synchronized through the released `41d8aa4` tip; authorized repository settings
were waived for `v0.1.0` after the available token received HTTP 403.

The authorized fast-forward aligned `master` and `develop`. Conventional CI, the Nix matrix, and Pages passed from
`master`. The BookStack canary passed at the same commit immediately before the cutover; a branch-specific repeat could
not be dispatched with the current token. Packagist now resolves both development branches to the release candidate.

### Slice 5 — First tag and publication verification

**Status:** Complete. Signed annotated tag `v0.1.0` points to `41d8aa4`. Packagist indexes it as stable version `0.1.0`
with the current source reference, description, dependencies, and license, and Composer exposes it as an installable
version. No GitHub Release object was requested or created.

## Initial audit evidence

| Command or source | Result |
| --- | --- |
| `git rev-list --left-right --count origin/master...origin/develop` | `0 57`; `master` is behind with no unique commits. |
| `git tag` and `gh release list` | No tags or releases. |
| `composer validate --strict` | Passed. |
| `composer audit --locked --no-dev` | Passed with no advisories. |
| `composer audit --locked` | Reported development-only Laravel advisories and abandoned `doctrine/annotations`. |
| `composer package:check` | Passed; 53 expected files and isolated PHPStan 1.12 consumer. |
| GitHub conventional CI run `31866628091` | Passed at `50e3e2e`. |
| GitHub Nix CI run `31866628125` | Passed every generated job at `50e3e2e`. |
| Packagist Composer metadata endpoint | `dev-master` points to `5cb24e9`; `dev-develop` points to `50e3e2e`. |
| GitHub branch API | `master` and `develop` are both reported as unprotected; repository rulesets list is empty. |
| GitHub Pages API | Returned `404` to the current credentials; hosted-site configuration was not verified. |

The initial audit did not fetch or modify remote branches, change GitHub settings, dispatch workflows, create a tag, or
publish a release. Later release slices changed the branch state and attempted the authorized settings recorded below;
the initial evidence remains here rather than being rewritten as if those findings had never existed.

## Default-branch cutover evidence

| Command or source | Result |
| --- | --- |
| GitHub branch API | `master` and `develop` both resolve to `1b0fb11510610a9a1fc7a3b31faabeb0cdfea24b`. |
| GitHub conventional CI run `31873777456` | Passed from `master` at `1b0fb11`. |
| GitHub Nix CI run `31873777510` | Passed every generated job from `master` at `1b0fb11`, including mutation. |
| GitHub Pages run `31873777475` | Passed from `master`; the guide is available at `https://jbboehr.github.io/phpstan-lost-in-translation/`. |
| GitHub BookStack run `31873535591` | Passed at `1b0fb11` under `develop` before the identical SHA became `master`. |
| Packagist metadata endpoint | `dev-master` and `dev-develop` resolve to `1b0fb11` with current metadata and licensing. |
| GitHub repository API | The selected description, homepage, protection, and master-labelled BookStack dispatch were rejected with HTTP 403; settings remained unchanged. |
| `git tag` and `gh release list` | No tags or releases exist. |

## First-release publication evidence

| Command or source | Result |
| --- | --- |
| GitHub tag API | Signed annotated tag `v0.1.0` has tag object `492ec92` and resolves to commit `41d8aa4`. |
| GitHub conventional CI run `31875010500` | Passed from `master` at the released commit. |
| GitHub Nix CI run `31875010484` | Passed every generated job from `master` at the released commit, including mutation. |
| GitHub Pages run `31875010487` | Passed from `master`; the hosted guide advertises the released `^0.1` constraint. |
| Packagist Composer metadata endpoint | Published `v0.1.0`, normalized to `0.1.0.0`, from source commit `41d8aa4`. |
| `composer show --all jbboehr/phpstan-lost-in-translation` | Exposes `v0.1.0` with the current description and license. |
| `gh release list` | Empty by decision; the tag is published without a GitHub Release object. |
