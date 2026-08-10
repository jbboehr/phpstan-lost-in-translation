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

namespace jbboehr\PHPStanLostInTranslation\Tests\CallRule;

use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidLocaleRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class InvalidLocaleRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new InvalidLocaleRule($this->getTranslationLoader()),
            ]),
        );
    }

    public function testInvalidChoices(): void
    {
        $this->analyse([
            __DIR__ . '/../data/invalid-locale.php',
        ], [
            [
                'Locale has no available translation strings: invalid_locale',
                4,
            ],
            [
                'Unknown locale: invalid_locale',
                4,
            ],
            [
                'Locale has no available translation strings: pt_BR',
                7,
            ],
            [
                'Locale has no available translation strings: pt_BR',
                13,
            ],
        ]);
    }

    public function testNamedArguments(): void
    {
        $this->analyse([
            __DIR__ . '/../data/named-arguments.php',
        ], [
            ['Locale has no available translation strings: pt_BR', 3],
            ['Locale has no available translation strings: pt_BR', 4],
            ['Locale has no available translation strings: pt_BR', 5],
            ['Locale has no available translation strings: pt_BR', 6],
            ['Locale has no available translation strings: pt_BR', 9],
            ['Locale has no available translation strings: pt_BR', 10],
            ['Locale has no available translation strings: pt_BR', 11],
            ['Locale has no available translation strings: pt_BR', 13],
            ['Locale has no available translation strings: pt_BR', 14],
            ['Locale has no available translation strings: pt_BR', 15],
        ]);
    }

    public function testFlexibleLocaleResolvesTranslations(): void
    {
        $this->analyse([
            __DIR__ . '/../data/flexible-locale.php',
        ], []);
    }
}
