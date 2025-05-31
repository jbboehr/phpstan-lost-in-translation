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
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidCharacterEncodingRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\JsonLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\PhpLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class InvalidCharacterEncodingRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new InvalidCharacterEncodingRule(),
            ]),
        );
    }

    public function createTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            langPath: __DIR__ . '/lang-invalid-character-encoding',
            baseLocale: 'en',
            phpLoader: new PhpLoader(),
            jsonLoader: new JsonLoader(),
        );
    }

    public function testInvalidCharacterEncoding(): void
    {
        $this->analyse([
            __DIR__ . '/data/invalid-character-encoding.php',
        ], [
            [
                'Invalid character encoding for key "messages.\xf0(\x8c\xbc"',
                3,
            ],
            [
                'Invalid character encoding for value "messages.\xf0(\x8c\xbc" in locale "ja"',
                3,
            ],
        ]);
    }
}
