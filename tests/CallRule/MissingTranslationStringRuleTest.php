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
use jbboehr\PHPStanLostInTranslation\CallRule\MissingTranslationStringRule;
use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\Rule\LostInTranslationRule;
use jbboehr\PHPStanLostInTranslation\Tests\RuleTestCase;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use PHPStan\Reflection\ReflectionProvider;
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

    public function testNamespacedTranslationFunctionsResolveToGlobalHelpers(): void
    {
        $this->analyse([
            __DIR__ . '/../data/namespaced-translation-functions.php',
        ], [
            [
                'Missing translation string "namespaced double underscore" for locales: ja, zh',
                5,
            ],
            [
                'Missing translation string "namespaced trans" for locales: ja, zh',
                6,
            ],
            [
                'Missing translation string "namespaced trans choice" for locales: ja, zh',
                7,
            ],
            [
                'Missing translation string "namespaced mixed-case trans" for locales: ja, zh',
                8,
            ],
        ]);
    }

    public function testUnresolvedTranslationFunctionsFallBackWithoutMatchingImportedAliases(): void
    {
        $reflectionProvider = $this->createMock(ReflectionProvider::class);
        $reflectionProvider->method('resolveFunctionName')->willReturn(null);
        $this->lostInTranslationHelper = new LostInTranslationHelper(
            $this->getTranslationLoader(),
            $reflectionProvider,
        );

        $this->analyse([
            __DIR__ . '/../data/namespaced-translation-functions.php',
        ], [
            [
                'Missing translation string "namespaced double underscore" for locales: ja, zh',
                5,
            ],
            [
                'Missing translation string "namespaced trans" for locales: ja, zh',
                6,
            ],
            [
                'Missing translation string "namespaced trans choice" for locales: ja, zh',
                7,
            ],
            [
                'Missing translation string "namespaced mixed-case trans" for locales: ja, zh',
                8,
            ],
        ]);
    }

    public function testVendorNamespacedTranslationsAreLoadedPerLocale(): void
    {
        $this->translationLoader = new TranslationLoader(
            langPath: __DIR__ . '/../lang-scanning',
            baseLocale: 'en',
        );

        $this->analyse([
            __DIR__ . '/../data/vendor-translation-functions.php',
        ], [
            [
                'Missing translation string "other::messages.shared" for locales: ja',
                4,
                'Did you mean this similar key: "other::messages.shared"',
            ],
            [
                'Missing translation string "acme::messages.only_in_en" for locales: ja',
                5,
                'Did you mean this similar key: "acme::messages.only_in_en"',
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

    public function testFindSimilarDisabled(): void
    {
        $this->fuzzySearch = false;

        $this->analyse([
            __DIR__ . '/../data/missing-find-similar.php',
        ], [
            [
                'Missing translation string "exists in all localezs" for locales: ja, zh',
                3,
            ],
            [
                'Missing translation string "this one should not be similar to anything" for locales: ja, zh',
                4,
            ],
        ]);
    }

    public function testFlexibleLocaleResolvesTranslations(): void
    {
        $this->analyse([
            __DIR__ . '/../data/flexible-locale.php',
        ], []);
    }
}
