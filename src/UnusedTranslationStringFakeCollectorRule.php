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
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Node\Expr\CallLike>
 */
final class UnusedTranslationStringFakeCollectorRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
        private readonly UnusedTranslationStringCollector $collector,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            if (!str_contains($scope->getFile(), 'blade-compiled')) {
                return [];
            }

            $call = $this->helper->parseCallLike($node, $scope);

            if (null !== $call) {
                $this->collector->push($call->possibleTranslations);
            }

            return [];
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
