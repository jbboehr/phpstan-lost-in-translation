<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXVI John Boehr & contributors
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

use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;

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
        }
    }

    public function testOnlySupportedTranslationPathsAreLoaded(): void
    {
        $loader = new TranslationLoader(
            langPath: __DIR__ . '/lang-scanning',
            baseLocale: 'en',
        );

        $this->assertSame(['en'], $loader->getFoundLocales());
        $this->assertSame('Root JSON translation', $loader->get('en', 'root'));
        $this->assertSame('Grouped PHP translation', $loader->get('en', 'messages.grouped'));
        $this->assertNull($loader->get('en', 'ignored'));
        $this->assertSame([], $loader->getErrors());
        $this->assertSame(
            [
                realpath(__DIR__ . '/lang-scanning/en.json'),
                realpath(__DIR__ . '/lang-scanning/en/messages.php'),
            ],
            $loader->getLocaleFiles()['en'],
        );
    }
}
