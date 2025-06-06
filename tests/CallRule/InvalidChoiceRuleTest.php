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
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidChoiceRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class InvalidChoiceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new InvalidChoiceRule(),
            ]),
        );
    }

    public function testInvalidChoices(): void
    {
        $this->analyse([
            __DIR__ . '/../data/invalid-choice.php',
        ], [
            [
                'Translation choice does not cover all possible cases for number of type: 3',
                7,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: 2',
                10,
                Utils::formatTipForKeyValue('en', '{4,*} There are many|{3} There are three'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: int',
                15,
                Utils::formatTipForKeyValue('en', '{4,*} There are many|{3} There are three'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: int<2, 4>',
                29,
                Utils::formatTipForKeyValue('en', '{2} There are two|{3} There are three'),
            ],
            [
                'Translation choice has non-numeric value: "a"',
                32,
                Utils::formatTipForKeyValue('en', '{2} two|{a} three'),
            ],
            [
                'Translation choice has non-numeric value: "a"',
                33,
                Utils::formatTipForKeyValue('en', '{2} two|{3,a} three'),
            ],
            [
                'Failed to parse translation choice: "{3 three"',
                36,
                Utils::formatTipForKeyValue('en', '{2} two|{3 three'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: 4',
                42,
                Utils::formatTipForKeyValue('en', '{1,3} two'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: 3',
                46,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
        ]);
    }
}
