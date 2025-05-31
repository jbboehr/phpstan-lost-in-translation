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

namespace jbboehr\PHPStanLostInTranslation\Rule;

use jbboehr\PHPStanLostInTranslation\Collector\LostInTranslationCollector;
use jbboehr\PHPStanLostInTranslation\DynamicTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\InvalidChoiceRule;
use jbboehr\PHPStanLostInTranslation\InvalidLocaleRule;
use jbboehr\PHPStanLostInTranslation\InvalidReplacementRule;
use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\MissingTranslationStringInBaseLocaleRule;
use jbboehr\PHPStanLostInTranslation\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\MemoizingContainer;
use PHPStan\DependencyInjection\ParameterNotFoundException;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Registry;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\IdentifierRuleError;

/**
 * @implements Rule<CollectedDataNode>
 */
final class LostInTranslationRule implements Rule
{
    /**
     * @var \WeakReference<Container>
     */
    private \WeakReference $registry;

    private const FLAG_MAP = [
        'disallowDynamicTranslationStrings' => DynamicTranslationStringRule::class,
        'invalidChoices' => InvalidChoiceRule::class,
        'invalidLocales' => InvalidLocaleRule::class,
        'invalidReplacements' => InvalidReplacementRule::class,
        'missingTranslationStringsInBaseLocale' => MissingTranslationStringInBaseLocaleRule::class,
        'missingTranslationStrings' => MissingTranslationStringRule::class,
    ];

    public function __construct(
        private readonly LostInTranslationHelper $helper,
        Container $container,
    ) {
        $this->container = \WeakReference::create($container);
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @throws ParameterNotFoundException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        $container = $this->container->get();

        if (null === $container) {
            return [];
        }

        $rules = [];

        foreach ($container->getParameter('lostInTranslation') as $key => $_) {
            if (isset(self::FLAG_MAP[$key])) {
                $rules[] = $container->getByType(self::FLAG_MAP[$key]);
            }
        }

        foreach ($node->get(LostInTranslationCollector::class) as $fileData) {
            foreach ($fileData as $moreFileData) {
                foreach ($moreFileData as $call) {
                    if (is_array($call)) {
                        $call = TranslationCall::fromJsonArray($call);
                    }

                    foreach ($rules as $rule) {
                        try {
                            $errors = array_merge(
                                $errors,
                                $rule->processCall($call),
                            );
                        } catch (\Throwable $e) {
                            ShouldNotHappenException::rethrow($e);
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
