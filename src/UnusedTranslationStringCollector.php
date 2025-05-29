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

namespace jbboehr\PHPStanLostInTranslation;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @phpstan-import-type PossibleTranslationRecordCollection from LostInTranslationHelper
 * @implements Collector<Node\Expr\CallLike, PossibleTranslationRecordCollection>
 */
final class UnusedTranslationStringCollector implements Collector
{
    /** @var ?PossibleTranslationRecordCollection */
    private ?array $queued = null;

    public function __construct(
        private readonly LostInTranslationHelper $helper,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        try {
            if (str_contains($scope->getFile(), 'blade-compiled')) {
                return [];
            }

            return self::merge(
                $this->helper->parseCallLike($node, $scope)?->possibleTranslations,
                $this->queued,
            );
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }

    /**
     * @phpstan-param PossibleTranslationRecordCollection $data
     */
    public function push(array $data): void
    {
        $this->queued = self::merge($this->queued, $data);
    }

    /**
     * @param ?PossibleTranslationRecordCollection $left
     * @param ?PossibleTranslationRecordCollection $right
     * @return ?PossibleTranslationRecordCollection
     */
    private static function merge(?array $left, ?array $right): ?array
    {
        if ($left === null || count($left) <= 0) {
            return $right;
        }

        if ($right === null || count($right) <= 0) {
            return $left;
        }

        foreach ($right as $key => $items) {
            $left[$key] = array_merge($left[$key] ?? [], $items);
        }

        return $left;
    }
}
