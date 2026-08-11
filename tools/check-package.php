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

const PACKAGE_NAME = 'jbboehr/phpstan-lost-in-translation';
const PACKAGE_VERSION = 'dev-package-check';
const PACKAGE_CHECK_PREFIX = 'phpstan-lost-in-translation-package-check-';

/**
 * @param list<string> $command
 *
 * @return array{exitCode: int, stdout: string, stderr: string}
 */
function runPackageCheckProcess(array $command, string $workingDirectory): array
{
    $stdout = tmpfile();
    $stderr = tmpfile();

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Unable to create process output files.');
    }

    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => $stdout,
            2 => $stderr,
        ],
        $pipes,
        $workingDirectory,
    );

    if (!is_resource($process)) {
        throw new RuntimeException(sprintf('Unable to start command: %s', formatPackageCheckCommand($command)));
    }

    fclose($pipes[0]);
    $exitCode = proc_close($process);

    rewind($stdout);
    rewind($stderr);
    $stdoutContents = stream_get_contents($stdout);
    $stderrContents = stream_get_contents($stderr);
    fclose($stdout);
    fclose($stderr);

    if ($stdoutContents === false || $stderrContents === false) {
        throw new RuntimeException('Unable to read process output.');
    }

    return [
        'exitCode' => $exitCode,
        'stdout' => $stdoutContents,
        'stderr' => $stderrContents,
    ];
}

/**
 * @param list<string> $command
 *
 * @return array{exitCode: int, stdout: string, stderr: string}
 */
function runSuccessfulPackageCheckProcess(array $command, string $workingDirectory): array
{
    $result = runPackageCheckProcess($command, $workingDirectory);

    if ($result['exitCode'] !== 0) {
        throw new RuntimeException(sprintf(
            "Command failed with exit code %d: %s\n%s%s",
            $result['exitCode'],
            formatPackageCheckCommand($command),
            $result['stdout'],
            $result['stderr'],
        ));
    }

    return $result;
}

/**
 * @param list<string> $command
 */
function formatPackageCheckCommand(array $command): string
{
    return implode(' ', array_map(static fn (string $argument): string => escapeshellarg($argument), $command));
}

/**
 * @return list<string>
 */
function getComposerCommand(): array
{
    $composerBinary = getenv('COMPOSER_BINARY');

    if (!is_string($composerBinary) || $composerBinary === '') {
        $composerBinary = 'composer';
    }

    if (is_file($composerBinary) && !is_executable($composerBinary)) {
        return [PHP_BINARY, $composerBinary];
    }

    return [$composerBinary];
}

/**
 * @return list<string>
 */
function inspectPackageArchive(string $archivePath): array
{
    $requiredFiles = [
        'CHANGELOG.md',
        'CODE_OF_CONDUCT.md',
        'CONTRIBUTING.md',
        'LICENSE.md',
        'README.md',
        'composer.json',
        'docs/CLA-v1.md',
        'docs/LICENSE_EXCEPTION.md',
        'docs/STEWARD.md',
        'extension.neon',
    ];
    $requiredFileSet = array_fill_keys($requiredFiles, true);
    $archive = new PharData($archivePath);
    $archivePrefix = str_replace('\\', '/', sprintf('phar://%s/', $archivePath));
    $entries = [];
    $sourceFileCount = 0;

    /** @var PharFileInfo $entry */
    foreach (new RecursiveIteratorIterator($archive) as $entry) {
        $entryPath = str_replace('\\', '/', $entry->getPathname());

        if (!str_starts_with($entryPath, $archivePrefix)) {
            throw new RuntimeException(sprintf('Unable to resolve archived path: %s', $entryPath));
        }

        $path = substr($entryPath, strlen($archivePrefix));

        if ($entry->isLink()) {
            throw new RuntimeException(sprintf('Package archive contains a symbolic link: %s', $path));
        }

        if ($path === '' || $path[0] === '/' || str_contains($path, '../')) {
            throw new RuntimeException(sprintf('Package archive contains an unsafe path: %s', $path));
        }

        if (isset($requiredFileSet[$path])) {
            $entries[] = $path;
            continue;
        }

        if (str_starts_with($path, 'src/')) {
            $entries[] = $path;
            ++$sourceFileCount;
            continue;
        }

        throw new RuntimeException(sprintf('Package archive contains an unexpected path: %s', $path));
    }

    foreach ($requiredFiles as $requiredFile) {
        if (!in_array($requiredFile, $entries, true)) {
            throw new RuntimeException(sprintf('Package archive is missing required path: %s', $requiredFile));
        }
    }

    if ($sourceFileCount === 0) {
        throw new RuntimeException('Package archive does not contain any source files.');
    }

    $composerContents = file_get_contents(sprintf('phar://%s/composer.json', $archivePath));

    if ($composerContents === false) {
        throw new RuntimeException('Unable to read composer.json from the package archive.');
    }

    $composer = json_decode($composerContents, true, flags: JSON_THROW_ON_ERROR);

    if (!is_array($composer)) {
        throw new RuntimeException('The archived composer.json is not an object.');
    }

    if (($composer['name'] ?? null) !== PACKAGE_NAME) {
        throw new RuntimeException('The archived Composer package name is incorrect.');
    }

    if (($composer['type'] ?? null) !== 'phpstan-extension') {
        throw new RuntimeException('The archived Composer package type is incorrect.');
    }

    if (($composer['license'] ?? null) !== 'AGPL-3.0-only WITH romic-exception') {
        throw new RuntimeException('The archived Composer license is incorrect.');
    }

    if (($composer['extra']['phpstan']['includes'] ?? null) !== ['extension.neon']) {
        throw new RuntimeException('The archived PHPStan extension registration is incorrect.');
    }

    sort($entries);

    return $entries;
}

function extractPackageArchive(string $archivePath, string $packageDirectory): void
{
    if (!mkdir($packageDirectory, 0700) && !is_dir($packageDirectory)) {
        throw new RuntimeException(sprintf('Unable to create package directory: %s', $packageDirectory));
    }

    $archive = new PharData($archivePath);

    if (!$archive->extractTo($packageDirectory)) {
        throw new RuntimeException('Unable to extract the package archive.');
    }
}

/**
 * @param array<string, mixed> $contents
 */
function writePackageCheckJson(string $path, array $contents): void
{
    $json = json_encode(
        $contents,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ) . "\n";

    writePackageCheckFile($path, $json);
}

function writePackageCheckFile(string $path, string $contents): void
{
    $written = file_put_contents($path, $contents, LOCK_EX);

    if ($written !== strlen($contents)) {
        throw new RuntimeException(sprintf('Unable to write package-check fixture: %s', $path));
    }
}

function createPackageConsumer(string $consumerDirectory, string $packageDirectory): void
{
    if (!mkdir($consumerDirectory, 0700) && !is_dir($consumerDirectory)) {
        throw new RuntimeException(sprintf('Unable to create consumer directory: %s', $consumerDirectory));
    }

    writePackageCheckJson($consumerDirectory . '/composer.json', [
        'name' => 'jbboehr/phpstan-lost-in-translation-package-check',
        'description' => 'Temporary consumer used to verify the packaged PHPStan extension',
        'type' => 'project',
        'repositories' => [
            [
                'type' => 'path',
                'url' => str_replace('\\', '/', $packageDirectory),
                'options' => [
                    'symlink' => false,
                    'versions' => [
                        PACKAGE_NAME => PACKAGE_VERSION,
                    ],
                ],
            ],
        ],
        'require-dev' => [
            PACKAGE_NAME => PACKAGE_VERSION,
            'phpstan/extension-installer' => '^1.4',
            'phpstan/phpstan' => '^1.12',
        ],
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
        'config' => [
            'allow-plugins' => [
                'phpstan/extension-installer' => true,
            ],
            'sort-packages' => true,
        ],
    ]);

    writePackageCheckFile(
        $consumerDirectory . '/phpstan.neon',
        <<<'NEON'
parameters:
    level: max
    paths:
        - source.php
    lostInTranslation:
        baseLocale: en
        fuzzySearch: false
NEON
        . "\n",
    );

    writePackageCheckFile(
        $consumerDirectory . '/source.php',
        <<<'PHP'
<?php

declare(strict_types=1);

function __(string $key): string
{
    return $key;
}

__('messages.runtime_smoke');
PHP
        . "\n",
    );
}

function assertInstalledPackage(string $consumerDirectory): void
{
    $installedPackage = $consumerDirectory . '/vendor/' . PACKAGE_NAME;

    if (!is_dir($installedPackage)) {
        throw new RuntimeException('Composer did not install the packaged extension.');
    }

    if (is_link($installedPackage)) {
        throw new RuntimeException('Composer symlinked the source package instead of copying the archive contents.');
    }

    foreach (['.codex', '.github', 'AGENTS.md', 'composer.lock', 'secrets', 'tests', 'tmp.md', 'tools'] as $path) {
        if (file_exists($installedPackage . '/' . $path)) {
            throw new RuntimeException(sprintf('Installed package contains a development-only path: %s', $path));
        }
    }
}

/**
 * @param array{exitCode: int, stdout: string, stderr: string} $result
 */
function assertStandardPackageDiagnostic(array $result): void
{
    if ($result['exitCode'] !== 1) {
        throw new RuntimeException(sprintf(
            "PHPStan JSON analysis exited with %d instead of 1.\n%s%s",
            $result['exitCode'],
            $result['stdout'],
            $result['stderr'],
        ));
    }

    $output = json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);

    if (!is_array($output) || !is_array($output['files'] ?? null)) {
        throw new RuntimeException('PHPStan JSON analysis did not return a file-error map.');
    }

    $messages = [];

    foreach ($output['files'] as $file) {
        if (!is_array($file) || !is_array($file['messages'] ?? null)) {
            throw new RuntimeException('PHPStan JSON analysis returned an invalid file-error entry.');
        }

        array_push($messages, ...$file['messages']);
    }

    if (count($messages) !== 1 || !is_array($messages[0] ?? null)) {
        throw new RuntimeException(sprintf(
            "Expected exactly one packaged-consumer diagnostic.\n%s%s",
            $result['stdout'],
            $result['stderr'],
        ));
    }

    if (($messages[0]['identifier'] ?? null) !== 'lostInTranslation.missingBaseLocaleTranslationString') {
        throw new RuntimeException(sprintf(
            "Packaged consumer returned the wrong diagnostic identifier.\n%s%s",
            $result['stdout'],
            $result['stderr'],
        ));
    }
}

/**
 * @param array{exitCode: int, stdout: string, stderr: string} $result
 */
function assertCustomPackageDiagnostic(array $result): void
{
    if ($result['exitCode'] !== 1) {
        throw new RuntimeException(sprintf(
            "PHPStan custom-formatter analysis exited with %d instead of 1.\n%s%s",
            $result['exitCode'],
            $result['stdout'],
            $result['stderr'],
        ));
    }

    $output = json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);
    $expected = [
        'lostInTranslation.missingTranslationString' => [],
        'lostInTranslation.missingBaseLocaleTranslationString' => [
            'en' => [
                'messages.runtime_smoke' => null,
            ],
        ],
    ];

    if ($output !== $expected) {
        throw new RuntimeException(sprintf(
            "Packaged custom formatter returned unexpected output.\n%s%s",
            $result['stdout'],
            $result['stderr'],
        ));
    }
}

function removePackageCheckDirectory(string $directory): void
{
    $temporaryDirectory = realpath(sys_get_temp_dir());
    $resolvedDirectory = realpath($directory);

    if (
        $temporaryDirectory === false
        || $resolvedDirectory === false
        || dirname($resolvedDirectory) !== $temporaryDirectory
        || !str_starts_with(basename($resolvedDirectory), PACKAGE_CHECK_PREFIX)
    ) {
        throw new RuntimeException(sprintf('Refusing to remove unexpected directory: %s', $directory));
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolvedDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    /** @var SplFileInfo $entry */
    foreach ($iterator as $entry) {
        if ($entry->isLink() || $entry->isFile()) {
            if (!unlink($entry->getPathname())) {
                throw new RuntimeException(sprintf('Unable to remove temporary file: %s', $entry->getPathname()));
            }

            continue;
        }

        if (!rmdir($entry->getPathname())) {
            throw new RuntimeException(sprintf('Unable to remove temporary directory: %s', $entry->getPathname()));
        }
    }

    if (!rmdir($resolvedDirectory)) {
        throw new RuntimeException(sprintf('Unable to remove temporary directory: %s', $resolvedDirectory));
    }
}

$repositoryDirectory = dirname(__DIR__);
$temporaryDirectory = sys_get_temp_dir() . '/' . PACKAGE_CHECK_PREFIX . bin2hex(random_bytes(8));
$archivePath = $temporaryDirectory . '/package.tar';
$packageDirectory = $temporaryDirectory . '/package';
$consumerDirectory = $temporaryDirectory . '/consumer';
$composerCommand = getComposerCommand();
$exitCode = 0;

try {
    if (!mkdir($temporaryDirectory, 0700) && !is_dir($temporaryDirectory)) {
        throw new RuntimeException(sprintf('Unable to create temporary directory: %s', $temporaryDirectory));
    }

    runSuccessfulPackageCheckProcess([
        ...$composerCommand,
        'archive',
        '--format=tar',
        '--dir=' . $temporaryDirectory,
        '--file=package',
    ], $repositoryDirectory);

    $entries = inspectPackageArchive($archivePath);
    extractPackageArchive($archivePath, $packageDirectory);
    createPackageConsumer($consumerDirectory, $packageDirectory);

    runSuccessfulPackageCheckProcess([
        ...$composerCommand,
        'update',
        '--prefer-dist',
        '--no-interaction',
        '--no-progress',
        '--no-blocking',
    ], $consumerDirectory);

    assertInstalledPackage($consumerDirectory);

    $phpstanCommand = [
        PHP_BINARY,
        $consumerDirectory . '/vendor/phpstan/phpstan/phpstan',
        'analyse',
        '--configuration=' . $consumerDirectory . '/phpstan.neon',
        '--no-progress',
        '--no-ansi',
    ];

    assertStandardPackageDiagnostic(runPackageCheckProcess([
        ...$phpstanCommand,
        '--error-format=json',
    ], $consumerDirectory));
    assertCustomPackageDiagnostic(runPackageCheckProcess([
        ...$phpstanCommand,
        '--error-format=lostInTranslationJson',
    ], $consumerDirectory));

    fwrite(STDOUT, sprintf(
        "Package archive contains %d expected files; the isolated PHPStan 1.12 consumer passed.\n",
        count($entries),
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Package check failed: %s\n", $exception->getMessage()));
    $exitCode = 1;
} finally {
    if (is_dir($temporaryDirectory)) {
        try {
            removePackageCheckDirectory($temporaryDirectory);
        } catch (Throwable $exception) {
            fwrite(STDERR, sprintf("Package check cleanup failed: %s\n", $exception->getMessage()));
            $exitCode = 1;
        }
    }
}

exit($exitCode);
