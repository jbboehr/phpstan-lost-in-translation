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
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringInBaseLocaleRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class MissingTranslationStringInBaseLocaleRuleTest extends RuleTestCase
{
    private string $baseLocale = 'en';

    public function createTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            langPath: __DIR__ . '/../lang',
            baseLocale: $this->baseLocale,
        );
    }

    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new MissingTranslationStringInBaseLocaleRule(
                    $this->getTranslationLoader(),
                ),
            ]),
        );
    }

    public function testMissingInBaseLocale(): void
    {
        $this->analyse([
            __DIR__ . '/../data/missing-in-base-locale.php',
        ], [
            [
                "Likely missing translation string \"messages.in_ja_and_zh\" for base locale: en",
                3,
            ],
        ]);
    }

    public function testFlexibleLocaleIsComparedToCanonicalBaseLocale(): void
    {
        $this->analyse([
            __DIR__ . '/../data/flexible-base-locale.php',
        ], [
            [
                "Likely missing translation string \"messages.in_ja_and_zh\" for base locale: en",
                3,
            ],
        ]);
    }

    public function testFilelessBaseLocaleIsIncludedInImplicitLookup(): void
    {
        $this->baseLocale = 'fr';

        $this->analyse([
            __DIR__ . '/../data/missing-in-base-locale.php',
        ], [
            [
                "Likely missing translation string \"messages.in_ja_and_zh\" for base locale: fr",
                3,
            ],
        ]);
    }

    public function testCanonicalBaseLocaleAliasIsNotDuplicated(): void
    {
        $this->baseLocale = 'EN';

        $this->analyse([
            __DIR__ . '/../data/missing-in-base-locale.php',
        ], [
            [
                "Likely missing translation string \"messages.in_ja_and_zh\" for base locale: EN",
                3,
            ],
        ]);
    }

    public function testExplicitLocaleDoesNotIncludeFilelessBaseLocale(): void
    {
        $this->baseLocale = 'fr';

        $this->analyse([
            __DIR__ . '/../data/explicit-locale-with-fileless-base.php',
        ], []);
    }
}
