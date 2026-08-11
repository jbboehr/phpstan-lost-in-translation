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
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidCharacterEncodingRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\MetadataRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Type\Constant\ConstantStringType;

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
                'Invalid character encoding for value "\xf0(\x8c\xbc" in locale "ja"',
                3,
            ],
        ]);
    }

    public function testInvalidValueMessageDoesNotSubstituteAFullSentenceKey(): void
    {
        $key = 'A real full sentence with real shit in it.';
        $value = "\xf0\x28\x8c\xbc";
        $errors = (new InvalidCharacterEncodingRule())->processCall(new TranslationCall(
            className: null,
            functionName: '__',
            file: __FILE__,
            line: 123,
            possibleTranslations: [
                $key => [['ja', $value]],
            ],
            keyType: new ConstantStringType($key),
        ));

        $this->assertCount(1, $errors);
        $this->assertSame(
            'Invalid character encoding for value "\xf0(\x8c\xbc" in locale "ja"',
            $errors[0]->getMessage(),
        );
        $this->assertInstanceOf(MetadataRuleError::class, $errors[0]);
        $this->assertSame(
            Utils::metadata(key: $key, locale: 'ja', value: $value),
            $errors[0]->getMetadata(),
        );
    }
}
