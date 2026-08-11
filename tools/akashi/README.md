# README verification

This isolated Composer project uses Akashi to discover explicitly marked PHP examples in the repository README, then
analyzes each example with PHPStan 2 and the extension's dedicated language fixture. Stable extension diagnostic
identifiers and their code-relative lines are compared with the external expectation map in the test; tool-only
annotations are not added to public code snippets.

Akashi requires PHP 8.2 and its PHPStan integration targets PHPStan 2, while the main project retains PHP 8.1, PHPStan
1.12, and PHPUnit 9 compatibility. Keep these dependencies isolated from the root development requirements.

Run the check from the repository root with:

```shell
nix develop .#documentation --command composer docs:check
```

The initial corpus covers translation-call diagnostics. Blade, configuration, translation-file, console-output, and
type-inference examples remain in the README but are not executable PHPStan examples in this harness.
