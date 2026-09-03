# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Add repeatable PHPBench coverage for fuzzy lookup, missing-key diagnostics, JSON and nested PHP catalogue loading,
  and unused-key analysis, with optional Perfidious software and hardware counters through Nix.
- Add `validateChoiceSyntax` as the clear configuration name for malformed choice conditions and invalid bounds.

### Changed

- Define configuration, diagnostic identifiers and metadata, registered error formats, and the `Identifier` diagnostic
  and metadata constants as the supported integration surface; other PHP types are now explicitly internal.

### Deprecated

- Deprecate the ambiguous `invalidChoices` configuration name in favor of `validateChoiceSyntax`; the legacy key remains
  supported, and setting either key to `false` disables syntax diagnostics.

### Removed

### Fixed

- Prevent invalid non-string translation values from aborting analysis when diagnostic rendering cannot JSON-encode
  them.
- Match Laravel's falsey-locale fallback and PHP translation-array lookup semantics, including non-empty array parents
  and groups, exact dotted-key precedence at the group root, nested segment traversal, and root JSON precedence over
  identically named grouped items.
- Deduplicate replacement diagnostics across constant-array variants.
- Restrict Blade diagnostic and usage bridges to Bladestan's compiled-filename convention.

### Security

[Unreleased]: https://github.com/jbboehr/phpstan-lost-in-translation/compare/v0.1.0...HEAD
