<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXVI John Boehr & contributors
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

namespace jbboehr\PHPStanLostInTranslation\Blade;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\MetadataRuleError;
use PHPStan\Rules\TipRuleError;

/**
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
        if (str_contains($scope->getFile(), 'blade-compiled')) {
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

        self::$queued = array_merge(self::$queued, $queued);

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
