<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXVI John Boehr & contributors
 *
 * SPDX-License-Identifier: AGPL-3.0-only WITH romic-exception
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License version 3,
 * as published by the Free Software Foundation, together with the Romic
 * Exception (an additional permission under section 7 of that license).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * and the Romic Exception along with this program.  If not, see
 * <http://www.gnu.org/licenses/> and docs/LICENSE_EXCEPTION.md.
 */
declare(strict_types=1);

use jbboehr\PHPStanLostInTranslation\Tools\Documentation\GeneratedDocumentationLinkChecker;

require __DIR__ . '/GeneratedDocumentationLinkChecker.php';

$arguments = $_SERVER['argv'] ?? $GLOBALS['argv'] ?? null;

if (
    !is_array($arguments)
    || !array_is_list($arguments)
    || 2 !== count($arguments)
    || !is_string($arguments[0])
    || !is_string($arguments[1])
) {
    fwrite(STDERR, sprintf("Usage: %s GENERATED-DOCUMENTATION-DIRECTORY\n", __FILE__));
    exit(2);
}

try {
    $errors = (new GeneratedDocumentationLinkChecker())->check($arguments[1]);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

if ([] !== $errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Generated documentation links are valid.\n");
