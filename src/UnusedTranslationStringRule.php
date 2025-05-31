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

use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @phpstan-import-type UsedTranslationRecord from UnusedTranslationStringCollector
 * @implements Rule<CollectedDataNode>
 */
final class UnusedTranslationStringRule implements Rule
{
    public function __construct(
        private readonly TranslationLoader $loader,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            /** @var array<string, list<list<UsedTranslationRecord>>> $data */
            $data = $node->get(UnusedTranslationStringCollector::class);

            /** @phpstan-var list<UsedTranslationRecord> $used */
            $used = [];

            /** @phpstan-var list<IdentifierRuleError> $errors */
            $errors = [];

            foreach ($data as $fileResults) {
                foreach ($fileResults as $results) {
                    foreach ($results as $result) {
                        $used[] = $result;
                    }
                }
            }

            $possiblyUnused = $this->loader->diffUsed($used);

            foreach ($possiblyUnused as $item) {
                ['locale' => $locale, 'key' => $key, 'file' => $file, 'line' => $line, 'candidate' => $candidate] = $item;

                $builder =  RuleErrorBuilder::message(sprintf(
                    'Possibly unused translation string %s for locale: %s',
                    json_encode($key, JSON_THROW_ON_ERROR),
                    join(', ', [$locale])
                ))
                    ->identifier('lostInTranslation.possiblyUnusedTranslationString')
                    ->file($file)
                    ->line($line);

                if (null !== $candidate) {
                    $builder->addTip(sprintf('Did you mean %s?', Utils::e($candidate['key'])));
                }

                $errors[] = $builder->build();
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
