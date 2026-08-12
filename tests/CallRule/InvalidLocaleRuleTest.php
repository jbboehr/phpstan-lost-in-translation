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
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidLocaleRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
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

    public function testFlexibleScriptLocaleResolvesTranslations(): void
    {
        $this->translationLoader = new TranslationLoader(
            langPath: __DIR__ . '/../lang-script-locales',
            baseLocale: 'en',
            fuzzySearch: false,
        );

        $this->analyse([
            __DIR__ . '/../data/flexible-script-locale.php',
        ], []);
    }

    public function testConfiguredLocaleAliasUsesItsTargetForValidation(): void
    {
        $this->translationLoader = new TranslationLoader(
            langPath: __DIR__ . '/../lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            localeAliases: [
                'de_informal' => 'de_DE',
            ],
        );

        $this->analyse([
            __DIR__ . '/../data/locale-alias.php',
        ], []);
    }
}
