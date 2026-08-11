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
use jbboehr\PHPStanLostInTranslation\CallRule\InvalidReplacementRule;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\Utils;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<LostInTranslationRule>
 */
class InvalidReplacementRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new LostInTranslationRule(
            $this->getLostInTranslationHelper(),
            CallRuleCollection::createFromArray([
                new InvalidReplacementRule(),
            ]),
        );
    }

    public function testInvalidReplacements(): void
    {
        $this->analyse([
            __DIR__ . '/../data/invalid-replacement.php',
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
            ],
            [
                'Unused translation replacement: "named"',
                10,
                Utils::formatTipForKeyValue('en', 'exists in all locales', 'exists in all locales'),
            ],
            [
                'Unused translation replacement: "shared"',
                13,
                Utils::formatTipForKeyValue('en', 'exists in all locales', 'exists in all locales'),
            ],
            [
                'Unused translation replacement: "shared"',
                13,
                Utils::formatTipForKeyValue('ja', 'exists in all locales', 'exists in all locales'),
            ],
            [
                'Unused translation replacement: "shared"',
                13,
                Utils::formatTipForKeyValue('zh', 'exists in all locales', 'exists in all locales'),
            ],
        ]);
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

    public function testEquivalentReplacementVariantsAreCountedOnce(): void
    {
        $this->analyse([
            __DIR__ . '/../data/replacement-variant-deduplication.php',
        ], [
            [
                'Replacement string matches multiple variants: "foo"',
                11,
                Utils::formatTipForKeyValue('en', ':foo :FOO'),
            ],
        ]);
    }
}
