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

$root = dirname(__DIR__, 2);
$phpstan = $root . '/vendor/phpstan/phpstan/phpstan';
$process = proc_open(
    [
        PHP_BINARY,
        $phpstan,
        'analyse',
        '--configuration=' . $root . '/tests/runtime-smoke/phpstan.neon',
        '--error-format=lostInTranslationJson',
        '--no-progress',
    ],
    [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes,
    $root,
);

if (!is_resource($process)) {
    throw new \RuntimeException('Failed to start PHPStan');
}

$output = stream_get_contents($pipes[1]);
$errorOutput = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if (false === $output || false === $errorOutput) {
    throw new \RuntimeException('Failed to read PHPStan output');
}

if (1 !== $exitCode) {
    throw new \RuntimeException(sprintf(
        "Expected PHPStan to report the smoke diagnostic, got exit code %d\n%s\n%s",
        $exitCode,
        $output,
        $errorOutput,
    ));
}

$decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

if (!is_array($decoded)) {
    throw new \RuntimeException('Custom formatter output was not a JSON object');
}

$missingByLocale = $decoded['lostInTranslation.missingBaseLocaleTranslationString'] ?? null;

if (!is_array($missingByLocale)) {
    throw new \RuntimeException('Custom formatter output did not contain base-locale diagnostics');
}

$missing = $missingByLocale['en'] ?? null;

if (!is_array($missing) || !array_key_exists('messages.runtime_smoke', $missing)) {
    throw new \RuntimeException(sprintf(
        "Custom formatter output did not contain the expected smoke diagnostic\n%s",
        $output,
    ));
}

fwrite(STDOUT, $output . PHP_EOL);
