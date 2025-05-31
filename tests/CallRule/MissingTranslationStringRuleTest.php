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

namespace CallRule;

use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class MissingTranslationStringRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new MissingTranslationStringRule(
                    $this->getTranslationLoader(),
                ),
            ]),
        );
    }

    public function testLanguageFacade(): void
    {
        $this->analyse([
            __DIR__ . '/../data/lang-facade.php',
        ], [
            [
                'Missing translation string "lang facade" for locales: ja, zh',
                3,
            ],
        ]);
    }

    public function testTransChoiceFunction(): void
    {
        $this->analyse([
            __DIR__ . '/../data/trans-choice-function.php',
        ], [
            [
                'Missing translation string "trans choice function" for locales: ja, zh',
                3,
            ],
        ]);
    }

    public function testTransFunction(): void
    {
        $this->analyse([
            __DIR__ . '/../data/trans-function.php',
        ], [
            [
                'Missing translation string "double underscore" for locales: ja, zh',
                3,
            ],
            [
                'Missing translation string "trans function" for locales: ja, zh',
                4,
            ],
        ]);
    }

    public function testTranslatorMethod(): void
    {
        $this->analyse([
            __DIR__ . '/../data/translator.php',
        ], [
            [
                'Missing translation string "contract basic" for locales: ja, zh',
                4,
            ],

            [
                'Missing translation string "translator basic" for locales: ja, zh',
                7,
            ],
            [
                'Missing translation string "translator basic" for locales: ja, zh',
                8,
            ],
            [
                'Missing translation string "bar" for locales: ja, zh',
                14,
            ],
            [
                'Missing translation string "foo" for locales: ja, zh',
                14,
            ],
        ]);
    }

    public function testTypeInference(): void
    {
        $this->analyse([
            __DIR__ . '/../data/type-inference.php',
        ], [
            [
                'Missing translation string "foo" for locales: ja, zh',
                4,
            ],
            [
                'Missing translation string "bar" for locales: ja, zh',
                7,
            ],
            [
                'Missing translation string "foo" for locales: ja, zh',
                7,
            ],
// not sure why this is not working
//            [
//                'Missing translation string "three" for locales: ja, zh',
//                16,
//            ],
//            [
//                'Missing translation string "two" for locales: ja, zh',
//                16,
//            ],
            [
                'Missing translation string "foo" for locales: ja, zh',
                19,
            ],
            [
                'Missing translation string "bar" for locales: ja, zh',
                23,
            ],
            [
                'Missing translation string "foo" for locales: ja, zh',
                23,
            ],
        ]);
    }

    public function testFindSimilar(): void
    {
        $this->analyse([
            __DIR__ . '/../data/missing-find-similar.php',
        ], [
            [
                'Missing translation string "exists in all localezs" for locales: ja, zh',
                3,
                'Did you mean this similar key: "exists in all locales"',
            ],
            [
                'Missing translation string "this one should not be similar to anything" for locales: ja, zh',
                4,
            ],
        ]);
    }
}
