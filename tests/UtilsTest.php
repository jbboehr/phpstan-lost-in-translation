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
    public function testEscapeInvalidUnicodeFallback(): void
    {
        $this->assertSame('"\\xc3("', Utils::e("\xc3\x28"));
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
