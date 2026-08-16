# README verification

This isolated Composer project uses Akashi to discover explicitly marked PHP examples in the README and user guide,
then analyzes each example with PHPStan 2 and the extension's dedicated language fixture. Stable extension diagnostic
identifiers and their code-relative lines are compared with the external expectation map in the test; tool-only
annotations are not added to public code snippets. Examples use Akashi's canonical invisible `example` metadata rather
than a project-specific legacy marker dialect.

This harness deliberately uses PHP 8.2, PHPStan 2, PHPUnit 11, and Laravel 12, while the main project retains PHP 8.1,
PHPStan 1.12, and PHPUnit 9 compatibility. Keep this fixed documentation-test stack isolated from the root development
requirements.

Run the check from the repository root with:

```shell
nix develop .#documentation --command composer docs:check
```

The corpus covers marked translation-call diagnostics and verifies that the public configuration reference has exactly
the keys declared by both the extension schema and its defaults. Blade, translation-file, console-output, and
type-inference examples remain documentation but are not executable PHPStan examples in this harness.
