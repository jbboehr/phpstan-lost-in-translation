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

namespace jbboehr\PHPStanLostInTranslation\Tests\Benchmark;

use jbboehr\PHPStanLostInTranslation\TranslationLoader\TranslationLoader;
use jbboehr\PHPStanLostInTranslation\UsedTranslationRecord;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;

final class TranslationLoaderBenchmark
{
    private const ENTRY_COUNT = 1000;

    private const LOCALES = ['en', 'ja', 'zh'];

    private string $fixturePath = '';

    private string $phpFixturePath = '';

    private TranslationLoader $loader;

    /** @var list<UsedTranslationRecord> */
    private array $used = [];

    public function __construct()
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'lost-in-translation-benchmark-');
        if (!is_string($temporaryPath) || !unlink($temporaryPath) || !mkdir($temporaryPath)) {
            throw new \RuntimeException('Failed to create the benchmark fixture directory');
        }

        $this->fixturePath = $temporaryPath;
        $translations = [];

        for ($index = 0; $index < self::ENTRY_COUNT; $index++) {
            $key = sprintf('benchmark sentence %04d', $index);
            $translations[$key] = sprintf('Benchmark value %04d', $index);

            if (0 === $index % 2) {
                $this->used[] = new UsedTranslationRecord($key, '*', __FILE__, __LINE__);
            }
        }

        $contents = json_encode($translations, JSON_THROW_ON_ERROR);

        foreach (self::LOCALES as $locale) {
            if (false === file_put_contents($this->fixturePath . '/' . $locale . '.json', $contents)) {
                throw new \RuntimeException(sprintf('Failed to write the %s benchmark fixture', $locale));
            }
        }

        $phpTemporaryPath = tempnam(sys_get_temp_dir(), 'lost-in-translation-php-benchmark-');
        if (!is_string($phpTemporaryPath) || !unlink($phpTemporaryPath) || !mkdir($phpTemporaryPath . '/en', recursive: true)) {
            throw new \RuntimeException('Failed to create the PHP benchmark fixture directory');
        }

        $this->phpFixturePath = $phpTemporaryPath;
        $phpTranslations = [];

        for ($index = 0; $index < self::ENTRY_COUNT; $index++) {
            $phpTranslations[sprintf('section_%04d', $index)] = [
                'label' => sprintf('Section label %04d', $index),
                'nested' => [
                    'value' => sprintf('Nested value %04d', $index),
                ],
            ];
        }

        $phpContents = "<?php\n\nreturn " . var_export($phpTranslations, true) . ";\n";
        if (false === file_put_contents($this->phpFixturePath . '/en/messages.php', $phpContents)) {
            throw new \RuntimeException('Failed to write the PHP benchmark fixture');
        }
    }

    public function __destruct()
    {
        if ('' !== $this->fixturePath) {
            foreach (self::LOCALES as $locale) {
                $path = $this->fixturePath . '/' . $locale . '.json';
                if (is_file($path)) {
                    unlink($path);
                }
            }

            if (is_dir($this->fixturePath)) {
                rmdir($this->fixturePath);
            }
        }

        if ('' !== $this->phpFixturePath) {
            $phpTranslationFile = $this->phpFixturePath . '/en/messages.php';
            if (is_file($phpTranslationFile)) {
                unlink($phpTranslationFile);
            }

            $phpLocalePath = $this->phpFixturePath . '/en';
            if (is_dir($phpLocalePath)) {
                rmdir($phpLocalePath);
            }

            if (is_dir($this->phpFixturePath)) {
                rmdir($this->phpFixturePath);
            }
        }
    }

    #[Iterations(5)]
    #[Revs(1)]
    public function benchCatalogueScan(): void
    {
        $loader = new TranslationLoader(
            langPath: $this->fixturePath,
            baseLocale: 'en',
        );

        if ('Benchmark value 0999' !== $loader->get('ja', 'benchmark sentence 0999')) {
            throw new \RuntimeException('The benchmark catalogue was not loaded completely');
        }
    }

    #[Iterations(5)]
    #[Revs(1)]
    public function benchNestedPhpCatalogueScan(): void
    {
        $loader = new TranslationLoader(
            langPath: $this->phpFixturePath,
            baseLocale: 'en',
        );

        if ('Nested value 0999' !== $loader->get('en', 'messages.section_0999.nested.value')) {
            throw new \RuntimeException('The nested PHP benchmark catalogue was not loaded completely');
        }
    }

    public function setUpUnusedDiff(): void
    {
        $this->loader = new TranslationLoader(
            langPath: $this->fixturePath,
            baseLocale: 'en',
            fuzzySearch: false,
        );
    }

    #[Iterations(5)]
    #[Revs(5)]
    #[BeforeMethods('setUpUnusedDiff')]
    public function benchUnusedDiff(): void
    {
        $unused = $this->loader->diffUsed($this->used);

        if (1500 !== count($unused)) {
            throw new \RuntimeException(sprintf('Expected 1500 unused records, got %d', count($unused)));
        }
    }
}
