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

/**
 * @return array<string, mixed>
 */
function readBookStackCanaryJson(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read canary output: %s', $path));
    }

    $output = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    if (!is_array($output)) {
        throw new RuntimeException(sprintf('Canary output is not a JSON object: %s', $path));
    }

    $result = [];

    foreach ($output as $key => $value) {
        if (!is_string($key)) {
            throw new RuntimeException(sprintf('Canary output is not a JSON object: %s', $path));
        }

        $result[$key] = $value;
    }

    return $result;
}

/**
 * @param array<string, mixed> $output
 */
function assertNoBookStackGlobalErrors(array $output, string $mode): void
{
    if (($output['errors'] ?? null) !== []) {
        throw new RuntimeException(sprintf(
            '%s analysis returned global or internal errors: %s',
            ucfirst($mode),
            json_encode($output['errors'] ?? null, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ));
    }
}

/**
 * @param array<string, mixed> $output
 *
 * @return list<array<string, mixed>>
 */
function getBookStackFileDiagnostics(array $output): array
{
    $files = $output['files'] ?? null;

    if (!is_array($files)) {
        throw new RuntimeException('PHPStan output does not contain a file-error map.');
    }

    $diagnostics = [];

    foreach ($files as $file => $fileResult) {
        if (!is_string($file) || !is_array($fileResult) || !is_array($fileResult['messages'] ?? null)) {
            throw new RuntimeException('PHPStan output contains an invalid file-error entry.');
        }

        foreach ($fileResult['messages'] as $diagnostic) {
            if (!is_array($diagnostic)) {
                throw new RuntimeException('PHPStan output contains an invalid diagnostic.');
            }

            $diagnostic['file'] = $file;
            $diagnostics[] = $diagnostic;
        }
    }

    return $diagnostics;
}

/**
 * @param list<array<string, mixed>> $diagnostics
 *
 * @return array<string, int>
 */
function countBookStackIdentifiers(array $diagnostics): array
{
    $counts = [];

    foreach ($diagnostics as $diagnostic) {
        $identifier = $diagnostic['identifier'] ?? null;

        if (!is_string($identifier)) {
            $identifier = '<none>';
        }

        $counts[$identifier] = ($counts[$identifier] ?? 0) + 1;
    }

    ksort($counts);

    return $counts;
}

/**
 * @param array<string, mixed> $diagnostic
 */
function getBookStackDiagnosticFingerprint(array $diagnostic): string
{
    return json_encode([
        $diagnostic['file'] ?? null,
        $diagnostic['line'] ?? null,
        $diagnostic['identifier'] ?? null,
        $diagnostic['message'] ?? null,
        $diagnostic['ignorable'] ?? null,
        $diagnostic['tip'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/**
 * @param list<array<string, mixed>> $diagnostics
 */
function assertBookStackDiagnostic(
    array $diagnostics,
    string $identifier,
    string $message,
    ?string $tipFragment = null,
    ?string $fileSuffix = null,
): void {
    foreach ($diagnostics as $diagnostic) {
        if (($diagnostic['identifier'] ?? null) !== $identifier || ($diagnostic['message'] ?? null) !== $message) {
            continue;
        }

        if ($tipFragment !== null) {
            $tip = $diagnostic['tip'] ?? null;

            if (!is_string($tip) || !str_contains($tip, $tipFragment)) {
                continue;
            }
        }

        if ($fileSuffix !== null) {
            $file = $diagnostic['file'] ?? null;

            if (!is_string($file) || !str_ends_with(str_replace('\\', '/', $file), $fileSuffix)) {
                continue;
            }
        }

        return;
    }

    throw new RuntimeException(sprintf(
        'Missing curated BookStack diagnostic %s (%s). Observed identifiers: %s',
        $identifier,
        $message,
        json_encode(countBookStackIdentifiers($diagnostics), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ));
}

/**
 * @param list<array<string, mixed>> $diagnostics
 * @param list<string>              $forbiddenIdentifiers
 */
function assertBookStackIdentifiersAbsent(array $diagnostics, array $forbiddenIdentifiers): void
{
    $counts = countBookStackIdentifiers($diagnostics);

    foreach ($forbiddenIdentifiers as $identifier) {
        if (isset($counts[$identifier])) {
            throw new RuntimeException(sprintf(
                'BookStack regression returned forbidden identifier %s %d time(s).',
                $identifier,
                $counts[$identifier],
            ));
        }
    }
}

/**
 * @param list<array<string, mixed>> $diagnostics
 */
function assertBookStackDiagnosticsUnique(array $diagnostics): void
{
    $fingerprints = [];

    foreach ($diagnostics as $diagnostic) {
        $fingerprint = getBookStackDiagnosticFingerprint($diagnostic);

        if (isset($fingerprints[$fingerprint])) {
            throw new RuntimeException(sprintf(
                'BookStack analysis returned an exact duplicate extension diagnostic: %s',
                $fingerprint,
            ));
        }

        $fingerprints[$fingerprint] = true;
    }
}

/**
 * @param array<string, mixed> $output
 */
function assertBookStackBaseline(array $output): void
{
    assertNoBookStackGlobalErrors($output, 'baseline');
    $diagnostics = getBookStackFileDiagnostics($output);

    if ($diagnostics !== []) {
        throw new RuntimeException(sprintf(
            'The pinned BookStack baseline is no longer clean: %s',
            json_encode(countBookStackIdentifiers($diagnostics), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ));
    }

    fwrite(STDOUT, "BookStack baseline: clean.\n");
}

/**
 * @param array<string, mixed> $output
 */
function assertBookStackApplication(array $output): void
{
    assertNoBookStackGlobalErrors($output, 'application');
    $diagnostics = getBookStackFileDiagnostics($output);
    $identifierCounts = countBookStackIdentifiers($diagnostics);
    $expectedIdentifiers = [
        'lostInTranslation.invalidReplacement.unused',
        'lostInTranslation.missingBaseLocaleTranslationString',
        'lostInTranslation.missingTranslationString',
    ];

    assertBookStackDiagnosticsUnique($diagnostics);

    if (array_keys($identifierCounts) !== $expectedIdentifiers) {
        throw new RuntimeException(sprintf(
            'Application-only translation analysis returned an unexpected identifier set: %s',
            json_encode($identifierCounts, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ));
    }

    $diagnosticCount = count($diagnostics);

    if ($diagnosticCount < 150 || $diagnosticCount > 210) {
        throw new RuntimeException(sprintf(
            'BookStack application analysis returned %d extension diagnostics; expected the broad range 150 through 210.',
            $diagnosticCount,
        ));
    }

    $unusedReplacementCount = $identifierCounts['lostInTranslation.invalidReplacement.unused'];

    if ($unusedReplacementCount < 110 || $unusedReplacementCount > 170) {
        throw new RuntimeException(sprintf(
            'BookStack application analysis returned %d unused-replacement diagnostics; '
                . 'expected the broad range 110 through 170.',
            $unusedReplacementCount,
        ));
    }

    $sortRuleDiagnostics = array_values(array_filter(
        $diagnostics,
        static fn (array $diagnostic): bool => is_string($diagnostic['file'] ?? null)
            && str_ends_with(
                str_replace('\\', '/', $diagnostic['file']),
                '/app/Sorting/SortRuleOperation.php',
            ),
    ));
    $expectedSortRuleCounts = [
        'lostInTranslation.missingBaseLocaleTranslationString' => 19,
        'lostInTranslation.missingTranslationString' => 19,
    ];
    $sortRuleCounts = countBookStackIdentifiers($sortRuleDiagnostics);

    if ($sortRuleCounts !== $expectedSortRuleCounts) {
        throw new RuntimeException(sprintf(
            'BookStack SortRuleOperation inference noise changed: %s',
            json_encode($sortRuleCounts, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ));
    }

    assertBookStackDiagnostic(
        $diagnostics,
        'lostInTranslation.missingBaseLocaleTranslationString',
        'Likely missing translation string "passwords.throttled" for base locale: en',
        fileSuffix: '/app/Access/Controllers/ForgotPasswordController.php',
    );
    assertBookStackDiagnostic(
        $diagnostics,
        'lostInTranslation.missingBaseLocaleTranslationString',
        'Likely missing translation string "entities.comment_deleted" for base locale: en',
        fileSuffix: '/app/Activity/Controllers/CommentController.php',
    );
    assertBookStackDiagnostic(
        $diagnostics,
        'lostInTranslation.missingBaseLocaleTranslationString',
        'Likely missing translation string "settings.sort_rule_op_name_asc" for base locale: en',
        fileSuffix: '/app/Sorting/SortRuleOperation.php',
    );
    assertBookStackDiagnostic(
        $diagnostics,
        'lostInTranslation.invalidReplacement.unused',
        'Unused translation replacement: "email"',
        'Locale: "ar", Key: "auth.reset_password_sent"',
        '/app/Access/Controllers/ForgotPasswordController.php',
    );

    fwrite(STDOUT, sprintf(
        "BookStack application translation analysis: %d extension diagnostics retained; "
            . "curated findings and known inference noise passed.\n",
        $diagnosticCount,
    ));
    fwrite(STDOUT, sprintf(
        "Observed application identifiers: %s\n",
        json_encode($identifierCounts, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ));
}

/**
 * @param list<array<string, mixed>> $diagnostics
 * @param list<array<string, mixed>> $diagnosticsToRemove
 *
 * @return list<array<string, mixed>>
 */
function subtractBookStackDiagnostics(array $diagnostics, array $diagnosticsToRemove): array
{
    $fingerprintsToRemove = [];

    foreach ($diagnosticsToRemove as $diagnostic) {
        $fingerprintsToRemove[getBookStackDiagnosticFingerprint($diagnostic)] = true;
    }

    $remainingDiagnostics = [];

    foreach ($diagnostics as $diagnostic) {
        $fingerprint = getBookStackDiagnosticFingerprint($diagnostic);

        if (isset($fingerprintsToRemove[$fingerprint])) {
            unset($fingerprintsToRemove[$fingerprint]);
            continue;
        }

        $remainingDiagnostics[] = $diagnostic;
    }

    if ($fingerprintsToRemove !== []) {
        throw new RuntimeException(sprintf(
            'Blade analysis did not reproduce %d application-only extension diagnostic(s).',
            count($fingerprintsToRemove),
        ));
    }

    return $remainingDiagnostics;
}

/**
 * @param array<string, mixed> $output
 * @param array<string, mixed> $applicationOutput
 */
function assertBookStackBlade(array $output, array $applicationOutput): void
{
    assertNoBookStackGlobalErrors($output, 'Blade');
    assertNoBookStackGlobalErrors($applicationOutput, 'application comparison');
    $diagnostics = getBookStackFileDiagnostics($output);
    $applicationDiagnostics = getBookStackFileDiagnostics($applicationOutput);
    $extensionDiagnostics = array_values(array_filter(
        $diagnostics,
        static fn (array $diagnostic): bool => is_string($diagnostic['identifier'] ?? null)
            && str_starts_with($diagnostic['identifier'], 'lostInTranslation.'),
    ));

    assertBookStackDiagnosticsUnique($extensionDiagnostics);
    assertBookStackDiagnosticsUnique($applicationDiagnostics);

    $bladeDiagnostics = subtractBookStackDiagnostics($extensionDiagnostics, $applicationDiagnostics);
    $pluralFormDiagnostics = array_values(array_filter(
        $bladeDiagnostics,
        static fn (array $diagnostic): bool => $diagnostic['identifier']
            === 'lostInTranslation.invalidChoice.missingPluralForm',
    ));
    $bladeDiagnosticCount = count($bladeDiagnostics);
    $nonPluralFormDiagnosticCount = $bladeDiagnosticCount - count($pluralFormDiagnostics);

    if ($nonPluralFormDiagnosticCount < 40 || $nonPluralFormDiagnosticCount > 100) {
        throw new RuntimeException(sprintf(
            'BookStack Blade analysis returned %d non-plural-form extension diagnostics; '
                . 'expected the broad range 40 through 100.',
            $nonPluralFormDiagnosticCount,
        ));
    }

    if (count($pluralFormDiagnostics) < 70 || count($pluralFormDiagnostics) > 110) {
        throw new RuntimeException(sprintf(
            'BookStack Blade analysis returned %d missing-plural-form diagnostics; expected the broad range 70 through 110.',
            count($pluralFormDiagnostics),
        ));
    }

    assertBookStackIdentifiersAbsent($bladeDiagnostics, [
        'lostInTranslation.invalidChoice.malformed',
        'lostInTranslation.invalidLocale.unknown',
        'lostInTranslation.translationLoaderError',
    ]);
    assertBookStackDiagnostic(
        $bladeDiagnostics,
        'lostInTranslation.invalidChoice.missingCase',
        'Explicit translation choice conditions do not cover all possible cases for number of type: int',
        'Locale: "cs", Key: "entities.search_total_results_found"',
    );
    assertBookStackDiagnostic(
        $bladeDiagnostics,
        'lostInTranslation.invalidChoice.nonNumeric',
        'Translation choice range must contain exactly two bounds; use "[2,4]" instead of "[2,3,4]" for contiguous values',
        'Locale: "sk", Key: "entities.x_books"',
    );
    assertBookStackDiagnostic(
        $bladeDiagnostics,
        'lostInTranslation.invalidChoice.missingPluralForm',
        'Translation choice provides 1 plural form, but locale "is" can select 2 forms',
        'Locale: "is", Key: "entities.x_books"',
        '/app/Users/Controllers/UserProfileController.php',
    );
    assertBookStackDiagnostic(
        $bladeDiagnostics,
        'lostInTranslation.invalidChoice.missingPluralForm',
        'Translation choice provides 1 plural form, but locale "de_informal" can select 2 forms',
        'Locale: "de_informal", Key: "entities.x_chapters"',
        '/app/Users/Controllers/UserProfileController.php',
    );
    assertBookStackDiagnostic(
        $bladeDiagnostics,
        'lostInTranslation.invalidReplacement.unused',
        'Unused translation replacement: "bookName"',
        'Locale: "id", Key: "entities.books_delete_explain"',
        '/app/Entities/Controllers/BookController.php',
    );
    assertBookStackDiagnostic(
        $bladeDiagnostics,
        'lostInTranslation.invalidReplacement.unused',
        'Unused translation replacement: "pageLink"',
        'Locale: "fr", Key: "components.image_uploaded_to"',
        '/app/Uploads/Controllers/ImageController.php',
    );
    assertBookStackDiagnostic(
        $bladeDiagnostics,
        'lostInTranslation.invalidReplacement.unused',
        'Unused translation replacement: "appName"',
        'Locale: "et", Key: "auth.user_invite_page_text"',
        '/app/Access/Controllers/UserInviteController.php',
    );
    assertBookStackDiagnostic(
        $bladeDiagnostics,
        'lostInTranslation.missingTranslationString',
        'Missing translation string "entities.books_sort_auto_sort" for locales: pt',
        fileSuffix: '/app/Sorting/BookSortController.php',
    );

    fwrite(STDOUT, sprintf(
        "BookStack Blade analysis: %d Blade-specific extension diagnostics retained after subtracting %d "
            . "application diagnostics; curated identifiers and bridge tips passed.\n",
        $bladeDiagnosticCount,
        count($applicationDiagnostics),
    ));
    fwrite(STDOUT, sprintf(
        "Observed Blade-specific identifiers: %s\n",
        json_encode(countBookStackIdentifiers($bladeDiagnostics), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ));
}

/**
 * @param array<string, mixed> $output
 */
function assertBookStackVersions(array $output): void
{
    $installed = $output['installed'] ?? null;

    if (!is_array($installed)) {
        throw new RuntimeException('Composer output does not contain an installed-package list.');
    }

    $versions = [];

    foreach ($installed as $package) {
        if (!is_array($package) || !is_string($package['name'] ?? null) || !is_string($package['version'] ?? null)) {
            throw new RuntimeException('Composer output contains an invalid installed-package entry.');
        }

        $versions[$package['name']] = $package['version'];
    }

    $expected = [
        'larastan/larastan' => 'v3.10.0',
        'laravel/framework' => 'v12.64.0',
        'livewire/livewire' => 'v4.4.0',
        'phpstan/phpstan' => '2.2.6',
        'squizlabs/php_codesniffer' => '4.0.2',
        'tomasvotruba/bladestan' => '0.11.7',
    ];

    foreach ($expected as $package => $version) {
        if (($versions[$package] ?? null) !== $version) {
            throw new RuntimeException(sprintf(
                'Canary resolved %s at %s instead of %s.',
                $package,
                $versions[$package] ?? '<missing>',
                $version,
            ));
        }
    }

    $extensionVersion = $versions['jbboehr/phpstan-lost-in-translation'] ?? null;

    if (
        !is_string($extensionVersion)
        || (
            $extensionVersion !== 'dev-bookstack-canary'
            && !str_starts_with($extensionVersion, 'dev-bookstack-canary ')
        )
    ) {
        throw new RuntimeException(sprintf(
            'Canary installed an unexpected extension version: %s.',
            $extensionVersion ?? '<missing>',
        ));
    }

    $expected['jbboehr/phpstan-lost-in-translation'] = $extensionVersion;
    ksort($expected);
    fwrite(STDOUT, sprintf(
        "Resolved canary versions: %s\n",
        json_encode($expected, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ));
}

try {
    $arguments = $_SERVER['argv'] ?? $GLOBALS['argv'] ?? null;

    if (!is_array($arguments) || !is_string($arguments[1] ?? null) || !is_string($arguments[2] ?? null)) {
        throw new InvalidArgumentException(
            'Usage: assert-output.php <baseline|application|versions> <json-file> '
                . '| blade <json-file> <application-json-file>',
        );
    }

    $mode = $arguments[1];

    if (($mode === 'blade' && count($arguments) !== 4) || ($mode !== 'blade' && count($arguments) !== 3)) {
        throw new InvalidArgumentException(
            'Usage: assert-output.php <baseline|application|versions> <json-file> '
                . '| blade <json-file> <application-json-file>',
        );
    }

    $output = readBookStackCanaryJson($arguments[2]);
    $applicationOutput = $mode === 'blade' && is_string($arguments[3] ?? null)
        ? readBookStackCanaryJson($arguments[3])
        : null;

    match ($mode) {
        'baseline' => assertBookStackBaseline($output),
        'application' => assertBookStackApplication($output),
        'blade' => assertBookStackBlade(
            $output,
            $applicationOutput ?? throw new InvalidArgumentException('Blade comparison output is required.'),
        ),
        'versions' => assertBookStackVersions($output),
        default => throw new InvalidArgumentException(sprintf('Unknown BookStack canary mode: %s', $mode)),
    };
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("BookStack canary assertion failed: %s\n", $exception->getMessage()));
    exit(1);
}
