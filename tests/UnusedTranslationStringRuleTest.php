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

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\JsonLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\PhpLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringCollector;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringRule;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<UnusedTranslationStringRule>
 */
class UnusedTranslationStringRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new UnusedTranslationStringRule(
            $this->getLostInTranslationHelper(),
        );
    }

    public function createLostInTranslationHelper(): LostInTranslationHelper
    {
        return new LostInTranslationHelper(
            new TranslationLoader(
                langPath: __DIR__ . '/lang-unused',
                baseLocale: null,
                phpLoader: new PhpLoader(),
                jsonLoader: new JsonLoader(),
            ),
        );
    }

    public function testPossiblyUnusedTranslations(): void
    {
        $this->analyse([
            __DIR__ . '/data/unused-translation-string.php',
        ], [
            [
                'Possibly unused translation string "unused_in_en" for locale: en',
                3,
                'Did you mean "used_in_en"?',
            ],
            [
                'Possibly unused translation string "unused_in_ja" for locale: ja',
                3
            ],
            [
                'Possibly unused translation string "used_in_en" for locale: ja',
                4
            ],
        ]);
    }

    public function getCollectors(): array
    {
        return [
            new UnusedTranslationStringCollector($this->getLostInTranslationHelper()),
        ];
    }
}
