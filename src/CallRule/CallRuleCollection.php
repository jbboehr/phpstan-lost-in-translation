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

namespace jbboehr\PHPStanLostInTranslation\CallRule;

use IteratorAggregate;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ParameterNotFoundException;
use Traversable;

/**
 * @internal
 * @implements IteratorAggregate<int, CallRuleInterface>
 */
final class CallRuleCollection implements IteratorAggregate, \Countable
{
    private const FLAG_MAP = [
        DynamicTranslationStringRule::class => ['disallowDynamicTranslationStrings'],
        InvalidCharacterEncodingRule::class => ['invalidCharacterEncodings'],
        InvalidChoiceRule::class => [
            'validateChoiceSyntax',
            'requireCompleteChoiceCoverage',
            'requireCompletePluralForms',
        ],
        InvalidLocaleRule::class => ['invalidLocales'],
        InvalidReplacementRule::class => ['invalidReplacements'],
        MissingTranslationStringInBaseLocaleRule::class => ['missingTranslationStringsInBaseLocale'],
        MissingTranslationStringRule::class => ['missingTranslationStrings'],
    ];

    /**
     * @var list<CallRuleInterface>
     */
    private array $rules = [];

    /**
     * @param list<CallRuleInterface> $rules
     * @return self
     */
    public static function createFromArray(array $rules): self
    {
        $self = new self(null);
        $self->rules = $rules;
        return $self;
    }

    public function __construct(
        ?Container $container,
    ) {
        if ($container === null) {
            return;
        }

        try {
            $flags = $container->getParameter('lostInTranslation');
        } catch (ParameterNotFoundException) {
            return;
        }

        if (!is_array($flags)) {
            return;
        }

        $flags['validateChoiceSyntax'] = true === ($flags['validateChoiceSyntax'] ?? true)
            && true === ($flags['invalidChoices'] ?? true);

        $rules = [];

        foreach (self::FLAG_MAP as $ruleClass => $ruleFlags) {
            foreach ($ruleFlags as $ruleFlag) {
                if (false !== ($flags[$ruleFlag] ?? false)) {
                    $rules[] = $container->getByType($ruleClass);
                    continue 2;
                }
            }
        }

        $this->rules = $rules;
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->rules);
    }

    public function count(): int
    {
        return count($this->rules);
    }
}
