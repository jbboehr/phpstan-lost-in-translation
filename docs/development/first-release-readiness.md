# First-release readiness

**Audited:** 2026-08-14 (America/Los_Angeles)<br>
**Source:** `develop` at `50e3e2e` (`Add mdBook user guide`)<br>
**Scope:** Package metadata, distributable archive, public documentation, dependency health, GitHub configuration,
Packagist state, and release validation

## Decision summary

The package is technically close to a first tagged release. Its current `develop` revision passes both conventional CI
and the exhaustive Nix matrix, the shipped archive passes an isolated PHPStan 1.12 consumer check, and the runtime
dependency audit is clean.

The release is not ready to tag directly from the current repository state. GitHub's default `master` branch is 57
commits behind `develop`, so Packagist presents obsolete package metadata by default and the new user guide cannot deploy
through the `master`-only Pages workflow. The version and changelog boundary are now resolved; the default-branch
cutover, repository settings, tag, and publication remain separately authorized actions.

The selected first version is `0.1.0`. It gives Composer users a stable install while retaining the project's honest
experimental, pre-1.0 compatibility signal. No tag, release, branch update, or public repository setting was changed by
this audit.

## Current state

| Area | Observation | Release effect |
| --- | --- | --- |
| Source | `origin/develop` matches audited HEAD. `origin/master` is an ancestor, 57 commits behind. | `master` can be fast-forwarded after release preparation and validation. |
| Tags and releases | The repository has no Git tags or GitHub releases. | The first release process has no legacy automation or numbering to preserve. |
| Composer metadata | `composer.json` has the current description, PHP `^8.1`, PHPStan `^1.12 || ^2.0`, and `AGPL-3.0-only WITH romic-exception`. | Suitable for a pre-1.0 release. |
| Package archive | `composer package:check` found the expected 53-file allowlisted archive and passed an isolated PHPStan 1.12 consumer. | The distributable contents and extension discovery are ready. |
| Runtime dependencies | `composer audit --locked --no-dev` reports no advisories. | No known advisory affects the shipped dependency closure. |
| Development dependencies | The full lock audit reports Laravel advisories and abandoned `doctrine/annotations`. Laravel 10 is present only under `require-dev` to test a supported compatibility boundary. | Do not ship these dependencies. Track supported-framework advisories without hiding them or dropping compatibility silently. |
| Conventional CI | Run `31866628091` passed at the audited commit. | Independent PHPUnit, PHPStan, php-cs-fixer, Composer validation, and runtime audit coverage is green. |
| Nix CI | Run `31866628125` passed all generated jobs at the audited commit, including mutation and the PHP/Laravel matrix. | Reproducible compatibility, documentation, consumer, coverage, property, and mutation checks are green. |
| BookStack canary | The workflow is manual and currently exists only on `develop`; GitHub cannot dispatch it by filename from the default branch. | Run it after `master` contains the workflow, before tagging. |
| User documentation | README and `docs/usage/` advertise `^0.1` for the selected release line. Pages deploys only from `master`, and the Pages API does not expose a configured site to the current credentials. | The constraint will resolve only after `0.1.0` is tagged; keep the validated `master`-to-tag interval short and verify Pages after updating `master`. |
| Packagist | Automatic updates are active. `dev-develop` resolves to the audited commit and carries current metadata. The default `dev-master` remains at `5cb24e9` from 2025 with the old description, license expression, dependencies, and README. | Update `master` before tagging so the public default and the tag are built from the reviewed package. |
| GitHub metadata | The repository description is the older missing-strings-only summary, no homepage is set, and GitHub reports `master` as unprotected. | Refresh the description/homepage and decide whether to protect `master` before or immediately after the first release. |
| Changelog | `CHANGELOG.md` is the intentionally empty Keep a Changelog skeleton. | Preserve the prior decision not to add entries before the first tag. Begin normal entries after the first-tag baseline. |

## Release blockers

### RR-01 — Reconcile `develop` with the default branch

**Status:** Open; this requires an explicitly authorized default-branch update after release-candidate validation.

`master` is the source of Packagist's default development version and the trigger for documentation deployment. Tagging
the current `develop` commit without first updating `master` would leave the repository landing page and Packagist's
default branch on the obsolete package.

Prepare and validate the release on `develop`, then fast-forward `master` to the reviewed commit. Do not merge an
unreviewed branch tip or tag a commit that has not passed the default-branch checks.

### RR-02 — Choose the first version and first-tag changelog boundary

**Status:** Resolved for release preparation. The selected version is `0.1.0`, and the public installation constraint is
`^0.1`.

Keep the changelog skeleton empty through the first tag, matching the existing project decision. Treat that tag as the
baseline and record subsequent notable changes under `Unreleased`. If a historical initial-release summary is desired,
that is a separate policy decision rather than an automatic reconstruction of the pre-tag history.

### RR-03 — Validate the release candidate on `master`

**Status:** Open; local release-preparation checks pass, but the prepared commit and later `master` tip require the full
release-candidate gates.

The audited `develop` commit is green, but the release candidate must include the installation and metadata edits and
must pass after becoming the default-branch tip. Require:

- conventional CI;
- the complete generated Nix matrix, including mutation;
- `composer package:check` against the final source tree;
- the manually dispatched pinned BookStack canary; and
- a successful documentation build and Pages deployment, or an explicit decision to release without hosted Pages.

### RR-04 — Make release-critical repository settings explicit

**Status:** Open; no repository settings were changed during local release preparation.

The GitHub API reports `master` as unprotected and no repository rulesets are configured. Before the first tag, decide
whether direct pushes and force-pushes to `master` should remain possible. This is an external repository-setting change
and must not be inferred from code work.

The public repository description should also be changed to the current Composer description, and the homepage should
point to the hosted guide once its URL is known. These metadata changes improve discovery but do not change the package.

## Not release blockers

- A dedicated release workflow is unnecessary for the first tag. A deliberate manual annotated tag and GitHub release
  are easier to audit; automation can follow an observed repetitive process.
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

With explicit authorization, fast-forward `master` to the reviewed release candidate. Then verify conventional CI, the
Nix matrix, Pages, and the manual BookStack canary from `master`. Refresh the GitHub description and homepage only with
authorization to change repository settings.

### Slice 5 — First tag and publication verification

With explicit authorization, create the selected tag on the validated `master` commit and publish the GitHub release.
Verify that Packagist discovers the tag, exposes the current dependencies and license, and accepts the documented
Composer install command. Do not create a tag or release as an incidental part of another slice.

## Evidence gathered

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

The audit did not fetch or modify remote branches, change GitHub settings, dispatch workflows, create a tag, or publish a
release.
