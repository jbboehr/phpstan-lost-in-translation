# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Add repeatable PHPBench coverage for fuzzy lookup, missing-key diagnostics, catalogue loading, and unused-key
  analysis, with optional Perfidious software and hardware counters through Nix.

### Changed

### Deprecated

### Removed

### Fixed

- Prevent invalid non-string translation values from aborting analysis when diagnostic rendering cannot JSON-encode
  them.
- Match Laravel's falsey-locale fallback and PHP translation-array lookup semantics, including non-empty array parents
  and exact literal dotted-key precedence.
- Deduplicate replacement diagnostics across constant-array variants.
- Restrict Blade diagnostic and usage bridges to Bladestan's compiled-filename convention.

### Security

[Unreleased]: https://github.com/jbboehr/phpstan-lost-in-translation/compare/v0.1.0...HEAD
