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

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use jbboehr\PHPStanLostInTranslation\Utils;
use Orchestra\Testbench\TestCase;

final class UtilsTest extends TestCase
{
    /**
     * @dataProvider provideFlexibleLocales
     */
    public function testCanonicalizeFlexibleLocale(string $locale, string $expected): void
    {
        $this->assertSame($expected, Utils::canonicalizeLocale($locale));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFlexibleLocales(): iterable
    {
        yield 'language case' => ['JA', 'ja'];
        yield 'regional case' => ['PT_br', 'pt_BR'];
        yield 'regional separator' => ['pt-br', 'pt_BR'];
        yield 'already canonical' => ['pt_BR', 'pt_BR'];
        yield 'script case' => ['ZH-hANS', 'zh_Hans'];
        yield 'script and region case' => ['SR-lATN-rs', 'sr_Latn_RS'];
        yield 'numeric region' => ['ES-419', 'es_419'];
    }

    /**
     * @dataProvider provideKnownFlexibleLocales
     */
    public function testCheckFlexibleLocaleExists(string $locale): void
    {
        $this->assertTrue(Utils::checkLocaleExists($locale));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideKnownFlexibleLocales(): iterable
    {
        yield 'script' => ['ZH-hANS'];
        yield 'script and region' => ['SR-lATN-rs'];
        yield 'numeric region' => ['ES-419'];
    }

    public function testCanonicalizeStrictLocalePreservesInput(): void
    {
        $this->assertSame('PT-br', Utils::canonicalizeLocale('PT-br', true));
    }

    public function testEscapeInvalidUnicodeFallback(): void
    {
        $this->assertSame('"\\xc3("', Utils::e("\xc3\x28"));
    }

    public function testEscapeBinaryUsesAsciiPrintableRange(): void
    {
        $this->assertSame('printable\\x00\\x7f\\xa0', Utils::escapeBinary("printable\x00\x7f\xa0"));
    }

    public function testFormatTipForKeyValue(): void
    {
        $this->assertStringContainsString('Key: ', Utils::formatTipForKeyValue('locale', 'key'));
        $this->assertStringContainsString('Key: ', Utils::formatTipForKeyValue('locale', 'key', 'value'));
        $this->assertStringContainsString('Value: ', Utils::formatTipForKeyValue('locale', 'key', 'value'));
    }

    public function testDetectLangPath(): void
    {
        $app = $this->app;
        $this->assertNotNull($app);

        $this->assertSame($app->langPath(), Utils::detectLangPath());

        $original = $app::getInstance();
        try {
            $app::setInstance();

            $this->assertSame('lang', Utils::detectLangPath());

            $app::setInstance(new Container());
            $this->assertSame('lang', Utils::detectLangPath());
        } finally {
            $app::setInstance($original);
        }
    }

    public function testDetectLangPathWithNoApplicationClass(): void
    {
        $this->assertSame('lang', Utils::detectLangPath(null));
    }

    public function testDetectLangPathWithUnbootedApplication(): void
    {
        $app = $this->createStub(\Illuminate\Contracts\Foundation\Application::class);
        $original = Application::getInstance();

        try {
            Application::setInstance($app);

            $this->assertSame('lang', Utils::detectLangPath());
        } finally {
            Application::setInstance($original);
        }
    }

    public function testDetectBaseLocaleWithNoApplication(): void
    {
        $this->assertSame('en', Utils::detectBaseLocale(null));
    }

    public function testDetectBaseLocaleWithUnbootedApplication(): void
    {
        $app = $this->createStub(\Illuminate\Contracts\Foundation\Application::class);
        $original = Application::getInstance();

        try {
            Application::setInstance($app);

            $this->assertSame('en', Utils::detectBaseLocale());
        } finally {
            Application::setInstance($original);
        }
    }
}
