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

namespace jbboehr\PHPStanLostInTranslation\Tests\TranslationLoader;

use jbboehr\PHPStanLostInTranslation\TranslationLoader\JsonLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

final class JsonLoaderTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testClosesHandleAfterSuccessfulAndFailingStreamingParses(): void
    {
        require_once __DIR__ . '/../fixtures/json-loader-close-interceptor.php';

        $path = tempnam(sys_get_temp_dir(), 'phpstan-lost-in-translation-');
        $this->assertIsString($path);

        try {
            $this->assertNotFalse(file_put_contents($path, '{"hello":"world"}'));

            $successfulResult = (new JsonLoader())->load(new SplFileInfo($path, '', basename($path)));

            $this->assertSame(['hello' => 'world'], $successfulResult->translations);
            $this->assertCount(1, $successfulResult->errors);
            $this->assertStringEndsWith(
                'JSON loader test close interceptor',
                $successfulResult->errors[0]->getMessage(),
            );

            $this->assertNotFalse(file_put_contents($path, '{'));

            $failingFile = new class ($path, '', basename($path)) extends SplFileInfo {
                public function getContents(): string
                {
                    return '{"hello":"world"}';
                }
            };
            $failingResult = (new JsonLoader())->load($failingFile);

            // The close interceptor throws from finally and replaces the parse exception; this proves cleanup ran.
            $this->assertSame(['hello' => 'world'], $failingResult->translations);
            $this->assertCount(1, $failingResult->errors);
            $this->assertStringEndsWith(
                'JSON loader test close interceptor',
                $failingResult->errors[0]->getMessage(),
            );
        } finally {
            unlink($path);
        }
    }
}
