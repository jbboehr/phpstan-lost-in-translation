<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

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
