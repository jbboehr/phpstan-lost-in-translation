<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXVI John Boehr & contributors
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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\TranslationLoader\PhpLoader;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\UsedTranslationRecord;

final class TranslationLoaderTest extends \PHPUnit\Framework\TestCase
{
    public function testMissingConfiguredLangPathThrows(): void
    {
        $langPath = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Configured language directory %s does not exist or is not a directory',
            json_encode($langPath, JSON_THROW_ON_ERROR),
        ));

        new TranslationLoader(
            langPath: $langPath,
            baseLocale: 'en',
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMissingConfiguredRelativeLangPathThrows(): void
    {
        $workingDirectory = getcwd();
        $temporaryDirectory = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));
        $langPath = 'missing-lang';

        $this->assertIsString($workingDirectory);
        $this->assertTrue(mkdir($temporaryDirectory));

        try {
            $this->assertTrue(chdir($temporaryDirectory));
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf(
                'Configured language directory %s does not exist or is not a directory',
                json_encode($langPath, JSON_THROW_ON_ERROR),
            ));

            new TranslationLoader(
                langPath: $langPath,
                baseLocale: 'en',
            );
        } finally {
            chdir($workingDirectory);
            rmdir($temporaryDirectory);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExistingRelativeLangPathLoadsTranslations(): void
    {
        $workingDirectory = getcwd();
        $temporaryDirectory = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));
        $langDirectory = $temporaryDirectory . '/lang';
        $translationFile = $langDirectory . '/en.json';

        $this->assertIsString($workingDirectory);
        $this->assertTrue(mkdir($langDirectory, recursive: true));

        try {
            $this->assertNotFalse(file_put_contents($translationFile, '{"relative":"Relative translation"}'));
            $this->assertTrue(chdir($temporaryDirectory));

            $loader = new TranslationLoader(
                langPath: 'lang',
                baseLocale: 'en',
                fuzzySearch: false,
            );

            $this->assertSame('Relative translation', $loader->get('en', 'relative'));
            $this->assertSame([realpath($translationFile)], $loader->getLocaleFiles()['en']);
        } finally {
            chdir($workingDirectory);
            unlink($translationFile);
            rmdir($langDirectory);
            rmdir($temporaryDirectory);
        }
    }

    public function testSymlinkedLangPathLoadsTranslations(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));
        $targetDirectory = $temporaryDirectory . '/language-files';
        $langPath = $temporaryDirectory . '/lang';
        $translationFile = $targetDirectory . '/en.json';

        $this->assertTrue(mkdir($targetDirectory, recursive: true));

        try {
            $this->assertNotFalse(file_put_contents($translationFile, '{"symlink":"Symlinked translation"}'));
            $this->assertTrue(symlink($targetDirectory, $langPath));

            $loader = new TranslationLoader(
                langPath: $langPath,
                baseLocale: 'en',
                fuzzySearch: false,
            );

            $this->assertSame('Symlinked translation', $loader->get('en', 'symlink'));
            $this->assertSame([realpath($translationFile)], $loader->getLocaleFiles()['en']);
        } finally {
            if (is_link($langPath)) {
                unlink($langPath);
            }
            unlink($translationFile);
            rmdir($targetDirectory);
            rmdir($temporaryDirectory);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMissingDetectedLangPathIsTreatedAsEmpty(): void
    {
        $workingDirectory = getcwd();
        $temporaryDirectory = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));

        $this->assertIsString($workingDirectory);
        $this->assertTrue(mkdir($temporaryDirectory));

        try {
            $this->assertTrue(chdir($temporaryDirectory));

            $loader = new TranslationLoader(baseLocale: 'en');

            $this->assertSame([], $loader->getFoundLocales());
            $this->assertNull($loader->get('en', 'messages.example'));
        } finally {
            chdir($workingDirectory);
            rmdir($temporaryDirectory);
        }
    }

    public function testParseKeyWithLeadingDots(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang',
            baseLocale: 'en',
        );

        $this->assertSame(['*', '.group.item'], $loader->parseKey('.group.item'));
    }

    public function testParseKeyCachesNamespacedAndPlainKeysSeparately(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang',
            baseLocale: 'en',
        );

        $this->assertSame(['vendor', 'messages.foo'], $loader->parseKey('vendor::messages.foo'));
        $this->assertSame(['*', 'messages.foo'], $loader->parseKey('messages.foo'));
        $this->assertSame(['vendor', 'messages.foo'], $loader->parseKey('vendor::messages.foo'));
    }

    public function testParseKeyCachesPlainAndNamespacedKeysSeparatelyInReverseOrder(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang',
            baseLocale: 'en',
        );

        $this->assertSame(['*', 'messages.foo'], $loader->parseKey('messages.foo'));
        $this->assertSame(['vendor', 'messages.foo'], $loader->parseKey('vendor::messages.foo'));
        $this->assertSame(['*', 'messages.foo'], $loader->parseKey('messages.foo'));
    }

    public function testNamespacedAndPlainTranslationLookupIsIndependentOfInsertionOrder(): void
    {
        foreach (
            [
                'plain key first' => ['messages.foo' => 'Plain translation', 'vendor::messages.foo' => 'Vendor translation'],
                'namespaced key first' => ['vendor::messages.foo' => 'Vendor translation', 'messages.foo' => 'Plain translation'],
            ] as $order => $translations
        ) {
            $loader = new TranslationLoader(
                langPath: __DIR__ . '/lang',
                baseLocale: 'en',
            );

            foreach ($translations as $key => $value) {
                $loader->add('en', $key, $value);
            }

            $this->assertSame('Plain translation', $loader->get('en', 'messages.foo'), $order);
            $this->assertSame('Vendor translation', $loader->get('en', 'vendor::messages.foo'), $order);
            $this->assertSame('vendor::messages.foo', $loader->searchForSimilarKeys('vendor::messages.foo'), $order);
        }
    }

    public function testOnlySupportedTranslationPathsAreLoaded(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-scanning',
            baseLocale: 'en',
        );

        $this->assertSame(['en', 'ja'], $loader->getFoundLocales());
        $this->assertSame('Root JSON translation', $loader->get('en', 'root'));
        $this->assertSame('Grouped PHP translation', $loader->get('en', 'messages.grouped'));
        $this->assertSame(
            'Shared vendor translation',
            $loader->get('en', 'acme::messages.shared'),
        );
        $this->assertSame(
            'Shared Japanese vendor translation',
            $loader->get('ja', 'acme::messages.shared'),
        );
        $this->assertSame('Other vendor translation', $loader->get('en', 'other::messages.shared'));
        $this->assertSame(
            'acme::messages.only_in_en',
            $loader->searchForSimilarKeys('acme::messages.only_in_enn'),
        );
        $this->assertNull($loader->get('en', 'ignored'));
        $this->assertSame([], $loader->getErrors());
        $this->assertSame(
            [
                realpath(__DIR__ . '/lang-scanning/en.json'),
                realpath(__DIR__ . '/lang-scanning/en/messages.php'),
                realpath(__DIR__ . '/lang-scanning/vendor/acme/en/messages.php'),
                realpath(__DIR__ . '/lang-scanning/vendor/other/en/messages.php'),
            ],
            $loader->getLocaleFiles()['en'],
        );
        $this->assertSame(
            [realpath(__DIR__ . '/lang-scanning/vendor/acme/ja/messages.php')],
            $loader->getLocaleFiles()['ja'],
        );
    }

    public function testVendorTranslationOverridesRetainNamespaceAndLocation(): void
    {
        $vendorOverride = realpath(__DIR__ . '/lang-scanning/vendor/acme/en/messages.php');
        $this->assertIsString($vendorOverride);

        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-scanning',
            baseLocale: 'en',
            fuzzySearch: false,
        );

        $this->assertSame('Shared vendor translation', $loader->get('en', 'acme::messages.shared'));
        $this->assertSame('English vendor translation', $loader->get('en', 'acme::messages.only_in_en'));
        $this->assertSame('Other vendor translation', $loader->get('en', 'other::messages.shared'));
        $this->assertSame([], $loader->getErrors());
        $this->assertContains($vendorOverride, $loader->getLocaleFiles()['en']);
        $this->assertSame([
            [
                'locale' => 'en',
                'key' => 'acme::messages.only_in_en',
                'file' => $vendorOverride,
                'line' => 5,
                'candidate' => null,
            ],
        ], $loader->diffUsed([
            new UsedTranslationRecord('root', '*', __FILE__, __LINE__),
            new UsedTranslationRecord('messages.grouped', '*', __FILE__, __LINE__),
            new UsedTranslationRecord('acme::messages.shared', '*', __FILE__, __LINE__),
            new UsedTranslationRecord('other::messages.shared', '*', __FILE__, __LINE__),
        ]));
    }

    public function testEmptyPhpTranslationGroupsAreIgnored(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-empty-arrays',
            baseLocale: 'en',
        );

        $this->assertSame('Still loaded', $loader->get('en', 'messages.nested.translation'));
        $this->assertNull($loader->get('en', 'messages.empty'));
        $this->assertNull($loader->get('en', 'messages.nested.empty'));

        $this->assertCount(1, $loader->getErrors());
        $this->assertSame(PhpLoader::IDENTIFIER, $loader->getErrors()[0]->getIdentifier());
        $this->assertSame('Invalid value: 1', $loader->getErrors()[0]->getMessage());
    }

    public function testArrayValuedTranslationsPreserveLookupsAndMarkReturnedLeavesUsed(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-array-values',
            baseLocale: 'en',
            fuzzySearch: false,
        );

        $this->assertSame(
            "Nested :nested\nTwo :name\nLabel :label",
            $loader->get('en', 'messages.options'),
        );
        $this->assertSame('Literal :literal', $loader->get('en', 'messages.options.one'));
        $this->assertSame('Two :name', $loader->get('en', 'messages.options.two'));
        $this->assertSame([], $loader->diffUsed([
            new UsedTranslationRecord('messages.options', 'en', __FILE__, __LINE__),
        ]));

        $unused = $loader->diffUsed([
            new UsedTranslationRecord('messages.options.one', 'en', __FILE__, __LINE__),
        ]);
        $this->assertSame([
            'messages.options.nested.label',
            'messages.options.two',
        ], array_column($unused, 'key'));
    }

    public function testFlexibleLocalesUseCanonicalLookupKeys(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-locales',
            baseLocale: 'EN-us',
            fuzzySearch: false,
        );

        $this->assertSame(['ja', 'pt_BR'], $loader->getFoundLocales());
        $this->assertTrue($loader->hasLocale('JA'));
        $this->assertSame('Japanese greeting', $loader->get('JA', 'greeting'));
        $this->assertTrue($loader->hasLocale('PT-br'));
        $this->assertSame('Brazilian Portuguese greeting', $loader->get('pt-BR', 'greeting'));
        $this->assertTrue($loader->hasLocale('en_US'));
        $this->assertTrue($loader->isBaseLocale('en_US'));
        $this->assertTrue($loader->isValidLocale('PT-br'));
    }

    public function testFlexibleScriptLocalesUseCanonicalLookupKeys(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-script-locales',
            baseLocale: 'ZH-hANS',
            fuzzySearch: false,
        );

        $this->assertSame(['sr_Latn_RS', 'zh_Hans'], $loader->getFoundLocales());
        $this->assertTrue($loader->hasLocale('ZH-hANS'));
        $this->assertTrue($loader->isBaseLocale('zh_hans'));
        $this->assertTrue($loader->isValidLocale('ZH-hANS'));
        $this->assertSame('Simplified Chinese greeting', $loader->get('zh-hans', 'greeting'));
        $this->assertTrue($loader->hasLocale('SR-lATN-rs'));
        $this->assertTrue($loader->isValidLocale('SR-lATN-rs'));
        $this->assertSame('Serbian Latin greeting', $loader->get('sr-latn-rs', 'messages.greeting'));
        $this->assertSame([], $loader->getErrors());
    }

    public function testStrictScriptLocalesRequireExactLookupKeys(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-script-locales',
            baseLocale: 'zh_Hans',
            fuzzySearch: false,
            strictLocales: true,
        );

        $this->assertTrue($loader->hasLocale('zh_Hans'));
        $this->assertSame('Simplified Chinese greeting', $loader->get('zh_Hans', 'greeting'));
        $this->assertFalse($loader->hasLocale('ZH-hANS'));
        $this->assertNull($loader->get('ZH-hANS', 'greeting'));
        $this->assertTrue($loader->isValidLocale('zh_Hans'));
        $this->assertFalse($loader->isValidLocale('ZH-hANS'));
    }

    public function testImplicitLookupLocalesIncludeAndSortFilelessBaseLocale(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang',
            baseLocale: 'fr',
            fuzzySearch: false,
        );

        $this->assertSame(['en', 'fr', 'ja', 'zh'], $loader->getLocalesForImplicitLookup());
    }

    public function testImplicitLookupLocalesDeduplicateCanonicalBaseLocale(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang',
            baseLocale: 'EN',
            fuzzySearch: false,
        );

        $this->assertSame(['en', 'ja', 'zh'], $loader->getLocalesForImplicitLookup());
    }

    public function testImplicitLookupLocalesPreserveStrictBaseLocale(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang',
            baseLocale: 'EN',
            fuzzySearch: false,
            strictLocales: true,
        );

        $this->assertSame(['EN', 'en', 'ja', 'zh'], $loader->getLocalesForImplicitLookup());
    }

    public function testStrictLocalesUseExactLookupKeys(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-locales',
            baseLocale: 'en_US',
            fuzzySearch: false,
            strictLocales: true,
        );

        $this->assertTrue($loader->hasLocale('ja'));
        $this->assertFalse($loader->hasLocale('JA'));
        $this->assertNull($loader->get('JA', 'greeting'));
        $this->assertTrue($loader->hasLocale('pt_BR'));
        $this->assertFalse($loader->hasLocale('pt-BR'));
        $this->assertNull($loader->get('pt-BR', 'greeting'));
        $this->assertTrue($loader->isBaseLocale('en_US'));
        $this->assertFalse($loader->isBaseLocale('en-US'));
        $this->assertTrue($loader->isValidLocale('pt_BR'));
        $this->assertFalse($loader->isValidLocale('PT-br'));
    }

    public function testLocaleAliasesOnlyAffectValidation(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            localeAliases: [
                'DE-INFORMAL' => 'de_DE',
            ],
        );

        $this->assertTrue($loader->isValidLocale('de_informal'));
        $this->assertSame('de_DE', $loader->resolveValidationLocale('de_informal'));
        $this->assertSame('de_DE', $loader->resolveValidationLocale('DE-INFORMAL'));
        $this->assertSame('fr', $loader->resolveValidationLocale('fr'));
        $this->assertTrue($loader->hasLocale('de_informal'));
        $this->assertSame('Informal German greeting', $loader->get('de_informal', 'greeting'));
        $this->assertFalse($loader->hasLocale('de_DE'));
        $this->assertNull($loader->get('de_DE', 'greeting'));
    }

    public function testLocaleAliasTargetsMustBeKnownLocales(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Locale alias target "also_invalid" for "de_informal" is not known to Symfony Intl',
        );

        new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            localeAliases: [
                'de_informal' => 'also_invalid',
            ],
        );
    }

    public function testStrictLocaleAliasesRequireExactApplicationKeys(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            strictLocales: true,
            localeAliases: [
                'de_informal' => 'de_DE',
            ],
        );

        $this->assertTrue($loader->isValidLocale('de_informal'));
        $this->assertSame('de_DE', $loader->resolveValidationLocale('de_informal'));
        $this->assertSame('DE_INFORMAL', $loader->resolveValidationLocale('DE_INFORMAL'));
        $this->assertFalse($loader->isValidLocale('DE_INFORMAL'));
    }

    public function testLocaleAliasesMustBeNonEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale aliases and their targets must be non-empty strings');

        new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            localeAliases: [
                'de_informal' => '',
            ],
        );
    }

    public function testLocaleAliasKeysMustBeStrings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale aliases and their targets must be non-empty strings');

        new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            localeAliases: [
                123 => 'de_DE',
            ],
        );
    }

    public function testLocaleAliasTargetsMustBeStrings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale aliases and their targets must be non-empty strings');

        new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            localeAliases: [
                'de_informal' => 123,
            ],
        );
    }

    public function testFlexibleLocaleAliasesMustHaveDistinctCanonicalKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Locale aliases "de_informal" and "DE-INFORMAL" both resolve to "de_INFORMAL"',
        );

        new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-aliases',
            baseLocale: 'en',
            fuzzySearch: false,
            localeAliases: [
                'de_informal' => 'de_DE',
                'DE-INFORMAL' => 'de_AT',
            ],
        );
    }

    public function testFlexibleLocaleAliasesAreMatchedWhenDiffingUsedTranslations(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-locales',
            baseLocale: 'en',
            fuzzySearch: false,
        );

        $this->assertSame([], $loader->diffUsed([
            new UsedTranslationRecord('greeting', 'JA', __FILE__, __LINE__),
            new UsedTranslationRecord('greeting', 'pt-BR', __FILE__, __LINE__),
        ]));
    }

    public function testFlexibleLocaleCollisionsProduceADeterministicDiagnostic(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-collision',
            baseLocale: 'en',
            fuzzySearch: false,
        );

        $this->assertSame(['en-US'], $loader->getFoundLocales());
        $this->assertSame('Dash locale', $loader->get('EN_us', 'collision'));
        $this->assertSame('Primary grouped locale', $loader->get('en_US', 'messages.primary_group'));
        $this->assertNull($loader->get('en_US', 'messages.secondary_group'));
        $this->assertCount(1, $loader->getErrors());

        $error = $loader->getErrors()[0];
        $this->assertSame(TranslationLoader::IDENTIFIER_LOCALE_CONFLICT, $error->getIdentifier());
        $this->assertSame(
            'Ignoring translation files for locale "en_US" because it resolves to "en_US", which is already provided by "en-US"',
            $error->getMessage(),
        );
    }

    public function testStrictLocaleSpellingsDoNotCollide(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-locale-collision',
            baseLocale: 'en',
            fuzzySearch: false,
            strictLocales: true,
        );

        $this->assertSame(['en-US', 'en_US'], $loader->getFoundLocales());
        $this->assertSame('Dash locale', $loader->get('en-US', 'collision'));
        $this->assertSame('Underscore locale', $loader->get('en_US', 'collision'));
        $this->assertSame('Primary grouped locale', $loader->get('en-US', 'messages.primary_group'));
        $this->assertSame('Secondary grouped locale', $loader->get('en_US', 'messages.secondary_group'));
        $this->assertSame([], $loader->getErrors());
    }

    public function testCaseOnlyLocaleCollisionUsesAStableTieBreaker(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));
        $uppercaseFile = $temporaryDirectory . '/JA.json';
        $lowercaseFile = $temporaryDirectory . '/ja.json';

        $this->assertTrue(mkdir($temporaryDirectory));

        try {
            $this->assertNotFalse(file_put_contents($uppercaseFile, '{"collision":"Uppercase locale"}'));

            if (file_exists($lowercaseFile)) {
                $this->markTestSkipped('The filesystem does not support paths that differ only by case');
            }

            $this->assertNotFalse(file_put_contents($lowercaseFile, '{"collision":"Lowercase locale"}'));

            $loader = new TranslationLoader(
                langPath: $temporaryDirectory,
                baseLocale: 'en',
                fuzzySearch: false,
            );

            $this->assertSame(['JA'], $loader->getFoundLocales());
            $this->assertSame('Uppercase locale', $loader->get('ja', 'collision'));
            $this->assertCount(1, $loader->getErrors());
            $this->assertSame(
                'Ignoring translation files for locale "ja" because it resolves to "ja", which is already provided by "JA"',
                $loader->getErrors()[0]->getMessage(),
            );
        } finally {
            if (file_exists($uppercaseFile)) {
                unlink($uppercaseFile);
            }
            if (file_exists($lowercaseFile)) {
                unlink($lowercaseFile);
            }
            rmdir($temporaryDirectory);
        }
    }

    public function testLocationFallsBackToPathnameWhenTranslationFileDisappearsDuringLoading(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8));
        $localeDirectory = $temporaryDirectory . '/en';
        $translationFile = $localeDirectory . '/messages.php';

        $this->assertTrue(mkdir($localeDirectory, recursive: true));

        try {
            $this->assertNotFalse(file_put_contents($translationFile, <<<'PHP'
                <?php
                unlink(__FILE__);
                return ['unused' => 'Translation'];
                PHP));

            $loader = new TranslationLoader(
                langPath: $temporaryDirectory,
                baseLocale: 'en',
                fuzzySearch: false,
            );
            $unused = $loader->diffUsed([]);

            $this->assertCount(1, $unused);
            $this->assertSame($translationFile, $unused[0]['file']);
        } finally {
            if (file_exists($translationFile)) {
                unlink($translationFile);
            }
            rmdir($localeDirectory);
            rmdir($temporaryDirectory);
        }
    }
}
