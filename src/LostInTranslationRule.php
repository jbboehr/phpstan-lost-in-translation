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
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<CollectedDataNode|Node\Expr\CallLike>
 */
final class LostInTranslationRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
        private readonly bool $useCollector = false,
    ) {
    }

    public function getNodeType(): string
    {
        if ($this->useCollector) {
            return CollectedDataNode::class;
        } else {
            return Node\Expr\CallLike::class;
        }
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            if ($node instanceof CollectedDataNode) {
                /** @var array<string, list<string>> $data */
                $data = $node->get(LostInTranslationCollector::class);

                $errors = [];

                foreach ($data as $results) {
                    foreach ($results as $result) {
                        // @TODO apparently we can only pass (unserialized) objects in debug mode... probably should revisit this
                        $result = unserialize($result);

                        assert($result instanceof TranslationCall);

                        $errors = array_merge(
                            $errors,
                            $this->helper->process($result)
                        );
                    }
                }

                return $errors;
            } else {
                $result = $this->helper->parseCallLike($node, $scope);

                if (null === $result) {
                    return [];
                }

                return $this->helper->process($result);
            }
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
