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
 * @implements Rule<CollectedDataNode>
 */
final class PossiblyUnusedTranslationRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
        private readonly bool $reportPossiblyUnusedTranslations = false,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            /** @TODO we could probably do this by unregistered in the phpstan config */
            if (!$this->reportPossiblyUnusedTranslations) {
                return [];
            }

            /** @var array<string, list<TranslationCall>> $data */
            $data = $node->get(LostInTranslationCollector::class);

            $errors = [];

            foreach ($data as $results) {
                foreach ($results as $result) {
                    $this->helper->markUsed($result);
                }
            }

            $possiblyUnused = $this->helper->diffUsed();

            foreach ($possiblyUnused as $item) {
                [$locale, $key] = $item;

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Possibly unused translation string %s for locale: %s',
                    json_encode($key, JSON_THROW_ON_ERROR),
                    join(', ', [$locale])
                ))
                    ->identifier('lostInTranslation.possiblyUnusedTranslationString')
                    ->build();
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
