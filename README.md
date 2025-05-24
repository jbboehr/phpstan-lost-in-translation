
# phpstan-lost-in-translation

[![ci](https://github.com/jbboehr/phpstan-lost-in-translation/actions/workflows/ci.yml/badge.svg)](https://github.com/jbboehr/phpstan-lost-in-translation/actions/workflows/ci.yml)
[![License: AGPL v3+](https://img.shields.io/badge/License-AGPL_v3%2b-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
![stability-experimental](https://img.shields.io/badge/stability-experimental-orange.svg)

## Installation

To use this extension, require it in [Composer](https://getcomposer.org/):

```bash
composer require --dev jbboehr/phpstan-lost-in-translation
```

If you also install [phpstan/extension-installer](https://github.com/phpstan/extension-installer) then you're all set!

### Manual installation

If you don't want to use `phpstan/extension-installer`, include `extension.neon` in your project's PHPStan config:

```neon
includes:
    - vendor/jbboehr/phpstan-lost-in-translation/extension.neon
```

## References

This project is based on and inspired by [coding-socks/lost-in-translation](https://github.com/coding-socks/lost-in-translation).

## License

This project is licensed under the [AGPL v3+](https://www.gnu.org/licenses/agpl-3.0) License - see the LICENSE.md file for details.
