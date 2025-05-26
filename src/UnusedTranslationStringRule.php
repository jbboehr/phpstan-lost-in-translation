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
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @phpstan-import-type PossibleTranslationRecordCollection from LostInTranslationHelper
 * @implements Rule<CollectedDataNode>
 */
final class UnusedTranslationStringRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            /** @var array<string, list<PossibleTranslationRecordCollection>> $data */
            $data = $node->get(UnusedTranslationStringCollector::class);

            $errors = [];

            foreach ($data as $results) {
                foreach ($results as $result) {
                    foreach ($result as $key => $items) {
                        foreach ($items as [$locale, $value]) {
                            $this->helper->markUsed($locale, $key);
                        }
                    }
                }
            }

            $possiblyUnused = $this->helper->diffUsed();

            foreach ($possiblyUnused as $item) {
                [$locale, $key, $file] = $item;

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Possibly unused translation string %s for locale: %s',
                    json_encode($key, JSON_THROW_ON_ERROR),
                    join(', ', [$locale])
                ))
                    ->identifier('lostInTranslation.possiblyUnusedTranslationString')
                    ->file($file)
                    ->line(-1)
                    ->build();
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
