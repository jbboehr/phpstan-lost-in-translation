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

namespace jbboehr\PHPStanLostInTranslation\Blade;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\MetadataRuleError;
use PHPStan\Rules\TipRuleError;

/**
 * @internal
 * @phpstan-type QueuedDiagnostic array{
 *     message: string,
 *     identifier: string,
 *     metadata: array<string, mixed>,
 *     tip: ?string
 * }
 * @phpstan-type CollectedDiagnostic array{
 *     message: string,
 *     identifier: string,
 *     metadata: array<string, mixed>,
 *     tip: ?string,
 *     file: string,
 *     line: int
 * }
 * @implements Collector<Node\Expr\CallLike, list<CollectedDiagnostic>>
 */
final class BladeDiagnosticCollector implements Collector
{
    /** Bladestan writes compiled views as <md5(source path)>-blade-compiled.php. */
    public const COMPILED_FILE_PATTERN = '~(?:^|[/\\\\])[a-f0-9]{32}-blade-compiled\.php$~D';

    private const TEMPLATE_LOCATION_PATTERN = '~/\*\* file: (.*?), line: (\d+) \*/~';

    /**
     * BladeStan analyses compiled templates in a derivative DI container, so
     * the queue must be shared by collector instances in the same worker.
     *
     * @phpstan-var list<QueuedDiagnostic>
     */
    private static array $queued = [];

    /**
     * Compiled templates are reused for every diagnostic raised during one
     * outer call, so retain their lines until that call drains the queue.
     *
     * @phpstan-var array<string, list<string>>
     */
    private static array $compiledFileLines = [];

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (1 === preg_match(self::COMPILED_FILE_PATTERN, $scope->getFile())) {
            return null;
        }

        // PHPStan evaluates rules before collectors for the same node. BladeStan's
        // rule fills the queue during its nested analysis, then this collector
        // drains it on that same outer view() call.
        $queued = self::$queued;
        self::$queued = [];
        self::$compiledFileLines = [];

        if ([] === $queued) {
            return null;
        }

        return array_map(
            static fn (array $diagnostic): array => [
                ...$diagnostic,
                'file' => $scope->getFile(),
                'line' => $node->getStartLine(),
            ],
            $queued,
        );
    }

    /**
     * @param list<IdentifierRuleError> $errors
     */
    public function push(array $errors, string $compiledFile, int $compiledLine): bool
    {
        $templateLocation = self::resolveTemplateLocation($compiledFile, $compiledLine);

        if (null === $templateLocation) {
            return false;
        }

        [$templateFile, $templateLine] = $templateLocation;
        $queued = [];

        foreach ($errors as $error) {
            $metadata = $error instanceof MetadataRuleError ? $error->getMetadata() : [];
            $metadata['template_file_path'] = $templateFile;
            $metadata['template_line'] = $templateLine;

            $queued[] = [
                'message' => $error->getMessage(),
                'identifier' => $error->getIdentifier(),
                'metadata' => $metadata,
                'tip' => $error instanceof TipRuleError ? $error->getTip() : null,
            ];
        }

        // BladeStan can repeat one nested analysis for the same outer call. Keep
        // different template locations or metadata, but queue an exact result once.
        foreach ($queued as $diagnostic) {
            if (!in_array($diagnostic, self::$queued, true)) {
                self::$queued[] = $diagnostic;
            }
        }

        return true;
    }

    /**
     * @return array{string, int}|null
     */
    private static function resolveTemplateLocation(string $compiledFile, int $compiledLine): ?array
    {
        $lines = self::$compiledFileLines[$compiledFile] ?? null;

        if (null === $lines) {
            if (!is_readable($compiledFile)) {
                return null;
            }

            $fileLines = file($compiledFile);

            if (false === $fileLines || [] === $fileLines) {
                return null;
            }

            $lines = $fileLines;
            self::$compiledFileLines[$compiledFile] = $lines;
        }

        $lineIndex = min(max(0, $compiledLine - 1), count($lines) - 1);

        for (; $lineIndex >= 0; --$lineIndex) {
            if (1 !== preg_match(self::TEMPLATE_LOCATION_PATTERN, $lines[$lineIndex], $matches)) {
                continue;
            }

            if ('' === $matches[1]) {
                return null;
            }

            return [$matches[1], (int) $matches[2]];
        }

        return null;
    }
}
