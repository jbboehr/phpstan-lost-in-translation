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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class LostInTranslationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return $this->createLostInTranslationRule();
    }

    public function testLanguageFacade(): void
    {
        $this->analyse([
            __DIR__ . '/data/lang-facade.php',
        ], [
            [
                'Missing translation string "lang facade" for locales: zh, ja',
                3,
            ],
        ]);
    }

    public function testTransChoiceFunction(): void
    {
        $this->analyse([
            __DIR__ . '/data/trans-choice-function.php',
        ], [
            [
                'Missing translation string "trans choice function" for locales: zh, ja',
                3,
            ],
        ]);
    }

    public function testTransFunction(): void
    {
        $this->analyse([
            __DIR__ . '/data/trans-function.php',
        ], [
            [
                'Missing translation string "double underscore" for locales: zh, ja',
                3,
            ],
            [
                'Missing translation string "trans function" for locales: zh, ja',
                4,
            ],
        ]);
    }

    public function testTranslatorMethod(): void
    {
        $this->analyse([
            __DIR__ . '/data/translator.php',
        ], [
            [
                'Missing translation string "contract basic" for locales: zh, ja',
                4,
            ],

            [
                'Missing translation string "translator basic" for locales: zh, ja',
                7,
            ],
            [
                'Missing translation string "translator basic" for locales: zh, ja',
                8,
            ],
            [
                'Missing translation string "bar" for locales: zh, ja',
                14,
            ],
            [
                'Missing translation string "foo" for locales: zh, ja',
                14,
            ],
            [
                "Likely missing translation string \"messages.in_ja_and_zh\" for base locale: en",
                19
            ],
        ]);
    }

    public function testMalformedReplacement(): void
    {
        $this->analyse([
            __DIR__ . '/data/malformed-replacement.php',
        ], [
            [
                'Unused translation replacement: "bar"',
                4,
                Utils::formatTipForKeyValue('en', 'exists in all locales', 'exists in all locales'),
            ],
            [
                'Unused translation replacement: "foo"',
                4,
                Utils::formatTipForKeyValue('en', 'exists in all locales', 'exists in all locales'),
            ],
            [
                'Replacement string matches multiple variants: "foo"',
                7,
                Utils::formatTipForKeyValue('en', ':foo :FOO', ':foo :FOO'),
            ]
        ]);
    }

    public function testMalformedChoice(): void
    {
        $this->analyse([
            __DIR__ . '/data/choice.php',
        ], [
            [
                'Translation choice does not cover all possible cases for number of type: 3',
                7,
                Utils::formatTipForKeyValue(
                    'en',
                    '{0} There are none|{1} There is one|[2] There are :count',
                    '{0} There are none|{1} There is one|[2] There are :count'
                ),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: 2',
                10,
                Utils::formatTipForKeyValue('en', '{4,*} There are many|{3} There are three', '{4,*} There are many|{3} There are three'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: int',
                15,
                Utils::formatTipForKeyValue('en', '{4,*} There are many|{3} There are three', '{4,*} There are many|{3} There are three'),
            ],
            [
                'Translation choice does not cover all possible cases for number of type: int<2, 4>',
                29,
                Utils::formatTipForKeyValue('en', '{2} There are two|{3} There are three', '{2} There are two|{3} There are three'),
            ]
        ]);
    }
}
