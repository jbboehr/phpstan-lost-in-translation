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
use jbboehr\PHPStanLostInTranslation\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\TranslationLoader;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class DynamicTranslationStringTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return $this->createLostInTranslationRule();
    }

    public function createLostInTranslationHelper(): LostInTranslationHelper
    {
        return new LostInTranslationHelper(
            new TranslationLoader(__DIR__ . '/lang'),
            disallowDynamicTranslationStrings: true,
            baseLocale: 'en',
            reportLikelyUntranslatedInBaseLocale: true,
        );
    }

    public function testDynamicTranslationString(): void
    {
        $this->analyse([
            __DIR__ . '/data/dynamic.php',
        ], [
            [
                'Disallowed dynamic translation string of type: string',
                5,
            ],
            [
                "Disallowed dynamic translation string of type: 'bar'|'foo'|Exception",
                8,
            ],
        ]);
    }
}
