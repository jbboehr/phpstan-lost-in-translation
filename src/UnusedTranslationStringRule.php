<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
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

namespace jbboehr\PHPStanLostInTranslation;

use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @internal
 * @implements Rule<CollectedDataNode>
 */
final class UnusedTranslationStringRule implements Rule
{
    /**
     * @param list<string> $analysedPaths
     * @param list<string> $analysedPathsFromConfig
     */
    public function __construct(
        private readonly TranslationLoader $loader,
        private readonly array $analysedPaths,
        private readonly array $analysedPathsFromConfig,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            $analysedPaths = array_values(array_unique($this->analysedPaths));
            $analysedPathsFromConfig = array_values(array_unique($this->analysedPathsFromConfig));
            sort($analysedPaths);
            sort($analysedPathsFromConfig);

            if ([] === $analysedPathsFromConfig || $analysedPaths !== $analysedPathsFromConfig) {
                return [];
            }

            /** @var array<string, list<list<array<string, string>|UsedTranslationRecord>>> $data */
            $data = $node->get(UnusedTranslationStringCollector::class);

            /** @phpstan-var list<UsedTranslationRecord> $used */
            $used = [];

            /** @phpstan-var list<IdentifierRuleError> $errors */
            $errors = [];

            foreach ($data as $fileResults) {
                foreach ($fileResults as $results) {
                    foreach ($results as $result) {
                        if (is_array($result)) {
                            $result = UsedTranslationRecord::fromJsonArray($result);
                        }
                        $used[] = $result;
                    }
                }
            }

            $possiblyUnused = $this->loader->diffUsed($used);

            foreach ($possiblyUnused as $item) {
                $builder =  RuleErrorBuilder::message(sprintf(
                    'Possibly unused translation string %s for locale: %s',
                    Utils::e($item['key']),
                    join(', ', [$item['locale']]),
                ))
                    ->identifier(Identifier::POSSIBLY_UNUSED_TRANSLATION_STRING)
                    ->file($item['file'])
                    ->line($item['line']);

                if (!empty($item['candidate'])) {
                    $builder->addTip(sprintf('Did you mean %s?', Utils::e($item['candidate'])));
                }

                $errors[] = $builder->build();
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
