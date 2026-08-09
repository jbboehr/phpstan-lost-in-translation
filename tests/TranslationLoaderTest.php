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
}
