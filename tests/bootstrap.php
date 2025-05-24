<?php

if (
    version_compare(\Composer\InstalledVersions::getVersion('laravel/framework') ?? '', '10', '>=') &&
    version_compare(\Composer\InstalledVersions::getVersion('laravel/framework') ?? '', '11', '<') &&
    version_compare(\Composer\InstalledVersions::getVersion('nikic/php-parser') ?? '', '5', '>=')
) {
    // some weird conflict with PHPStan/PhpParser
    /** @var \Composer\Autoload\ClassLoader $loader */
    $loader = require __DIR__ . '/../vendor/autoload.php';
    $loader->setPsr4('PhpParser\\', [
        __DIR__ . '/../vendor/phpstan/phpstan/phpstan.phar/vendor/nikic/php-parser/src'
    ]);
}
