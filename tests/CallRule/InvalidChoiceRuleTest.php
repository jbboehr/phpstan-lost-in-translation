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
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
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
            [
                'Translation choice does not cover all possible cases for number of type: 3',
                51,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: 3',
                52,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: 3',
                53,
                Utils::formatTipForKeyValue('en', '{0} There are none|{1} There is one|[2] There are :count'),
            ],
            [
                'Translation choice has non-numeric value: "3,4"',
                56,
                Utils::formatTipForKeyValue('sk', '[2,3,4] There are :count books'),
            ],
            [
                'Translation choice has non-numeric value: "a"',
                59,
                Utils::formatTipForKeyValue('en', '{a} one|{b} two'),
            ],
            [
                'Translation choice has non-numeric value: "b"',
                59,
                Utils::formatTipForKeyValue('en', '{a} one|{b} two'),
            ],
            [
                'Failed to parse translation choice: "  {3 three"',
                62,
                Utils::formatTipForKeyValue('en', '  {3 three'),
            ],
        ]);
    }

    public function testLaravelCompatibleUnconditionedChoices(): void
    {
        $this->analyse([
            __DIR__ . '/../data/valid-unconditioned-choice.php',
        ], []);
    }

    public function testSourceStringFallbackWithFilelessBaseLocale(): void
    {
        $this->translationLoader = new TranslationLoader(
            langPath: __DIR__ . '/../lang',
            baseLocale: 'fr',
        );

        $this->analyse([
            __DIR__ . '/../data/fileless-base-fallback.php',
        ], []);
    }
}
