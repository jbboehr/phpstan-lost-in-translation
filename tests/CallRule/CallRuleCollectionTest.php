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

namespace jbboehr\PHPStanLostInTranslation\Tests\CallRule;

use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidChoiceRule;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ParameterNotFoundException;
use PHPUnit\Framework\TestCase;

class CallRuleCollectionTest extends TestCase
{
    public function testMissingParameterDoesNotThrow(): void
    {
        $mock = $this->createMock(Container::class);
        $mock->method('getParameter')
            /** @phpstan-ignore-next-line phpstanApi.constructor */
            ->willThrowException(new ParameterNotFoundException('lostInTranslation'));

        $collection = new CallRuleCollection($mock);
        $this->assertCount(0, $collection);
    }

    public function testNonArrayParameterDoesNotThrow(): void
    {
        $mock = $this->createMock(Container::class);
        $mock->method('getParameter')
            ->willReturn('foo');

        $collection = new CallRuleCollection($mock);
        $this->assertCount(0, $collection);
    }

    /**
     * @dataProvider choiceFlagCombinations
     * @param array<string, bool> $flags
     */
    public function testChoiceRuleSelectionUsesTheUnionOfIndependentFlags(array $flags, bool $expected): void
    {
        $rule = new InvalidChoiceRule();
        $mock = $this->createMock(Container::class);
        $mock->method('getParameter')
            ->willReturn($flags);
        $mock->method('getByType')
            ->with(InvalidChoiceRule::class)
            ->willReturn($rule);

        $collection = new CallRuleCollection($mock);

        $this->assertSame(
            $expected ? [$rule] : [],
            array_values(iterator_to_array($collection)),
        );
    }

    /**
     * @return iterable<string, array{array<string, bool>, bool}>
     */
    public static function choiceFlagCombinations(): iterable
    {
        yield 'all disabled' => [[
            'invalidChoices' => false,
            'requireCompleteChoiceCoverage' => false,
            'requireCompletePluralForms' => false,
        ], false];
        yield 'syntax only' => [[
            'invalidChoices' => true,
            'requireCompleteChoiceCoverage' => false,
            'requireCompletePluralForms' => false,
        ], true];
        yield 'canonical syntax only' => [[
            'validateChoiceSyntax' => true,
            'requireCompleteChoiceCoverage' => false,
            'requireCompletePluralForms' => false,
        ], true];
        yield 'legacy syntax switch can disable the canonical switch' => [[
            'invalidChoices' => false,
            'validateChoiceSyntax' => true,
            'requireCompleteChoiceCoverage' => false,
            'requireCompletePluralForms' => false,
        ], false];
        yield 'coverage only' => [[
            'invalidChoices' => false,
            'requireCompleteChoiceCoverage' => true,
            'requireCompletePluralForms' => false,
        ], true];
        yield 'plural only' => [[
            'invalidChoices' => false,
            'requireCompleteChoiceCoverage' => false,
            'requireCompletePluralForms' => true,
        ], true];
        yield 'syntax and coverage' => [[
            'invalidChoices' => true,
            'requireCompleteChoiceCoverage' => true,
            'requireCompletePluralForms' => false,
        ], true];
        yield 'syntax and plural' => [[
            'invalidChoices' => true,
            'requireCompleteChoiceCoverage' => false,
            'requireCompletePluralForms' => true,
        ], true];
        yield 'coverage and plural' => [[
            'invalidChoices' => false,
            'requireCompleteChoiceCoverage' => true,
            'requireCompletePluralForms' => true,
        ], true];
        yield 'all enabled' => [[
            'invalidChoices' => true,
            'requireCompleteChoiceCoverage' => true,
            'requireCompletePluralForms' => true,
        ], true];
    }
}
