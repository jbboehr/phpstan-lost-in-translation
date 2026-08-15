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

use Illuminate\Support\Arr;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\LoadResult;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\PhpLoader;
use PHPStan\Rules\LineRuleError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

final class PhpLoaderTest extends TestCase
{
    public function testLoadsFlattenedTranslationsWithTheirSourceLines(): void
    {
        $path = __DIR__ . '/../lang/en/messages.php';
        $result = (new PhpLoader())->load(new SplFileInfo($path, '', basename($path)));

        $this->assertSame([
            'messages.only_in_en' => 'only_in_en',
            'messages.exists_in_all_locales' => 'exists_in_all_locales',
        ], $result->translations);
        $this->assertSame([
            'messages.only_in_en' => 2,
            'messages.exists_in_all_locales' => 3,
        ], $result->locations);
        $this->assertSame([], $result->errors);
    }

    public function testIgnoresEmptyArraysAndReportsInvalidLeaves(): void
    {
        $path = __DIR__ . '/../lang-empty-arrays/en/messages.php';
        $result = (new PhpLoader())->load(new SplFileInfo($path, '', basename($path)));

        $this->assertSame([
            'messages.nested' => 'Still loaded',
            'messages.nested.translation' => 'Still loaded',
        ], $result->translations);
        $this->assertSame(['messages.nested'], $result->arrayKeys);
        $this->assertCount(1, $result->errors);
        $this->assertSame('Invalid value: 1', $result->errors[0]->getMessage());
        $this->assertInstanceOf(LineRuleError::class, $result->errors[0]);
        $this->assertSame(7, $result->errors[0]->getLine());
    }

    public function testReportsUnencodableInvalidValuesWithoutThrowing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpstan-lost-in-translation-');
        $this->assertIsString($path);

        try {
            $this->assertNotFalse(file_put_contents(
                $path,
                "<?php\n\$stream = fopen('php://memory', 'r');\nreturn [\n"
                . "    'infinite' => INF,\n"
                . "    'not_a_number' => NAN,\n"
                . "    'stream' => \$stream,\n"
                . "];\n",
            ));

            $result = (new PhpLoader())->load(new SplFileInfo($path, '', basename($path)));

            $this->assertSame([], $result->translations);
            $this->assertSame([
                'Invalid value: INF',
                'Invalid value: NAN',
                'Invalid value: resource (stream)',
            ], array_map(static fn ($error): string => $error->getMessage(), $result->errors));
        } finally {
            unlink($path);
        }
    }

    public function testPreservesArrayParentsAndLiteralDottedItemPrecedence(): void
    {
        $path = __DIR__ . '/../lang-array-values/en/messages.php';
        $runtimeTranslations = require $path;
        $result = (new PhpLoader())->load(new SplFileInfo($path, '', basename($path)));

        $this->assertIsArray($runtimeTranslations);
        $this->assertSame('Literal :literal', Arr::get($runtimeTranslations, 'options.one'));
        $this->assertSame($runtimeTranslations['options'], Arr::get($runtimeTranslations, 'options'));
        $this->assertSame([
            'messages.options.one' => 'Literal :literal',
            'messages.options' => "Nested :nested\nTwo :name\nLabel :label",
            'messages.options.two' => 'Two :name',
            'messages.options.nested' => 'Label :label',
            'messages.options.nested.label' => 'Label :label',
        ], $result->translations);
        $this->assertSame([
            'messages.options.one' => 4,
            'messages.options.two' => 7,
            'messages.options.nested.label' => 9,
            'messages.options.nested' => 8,
            'messages.options' => 5,
        ], $result->locations);
        $this->assertSame([
            'messages.options',
            'messages.options.nested',
        ], $result->arrayKeys);
        $this->assertSame([], $result->errors);
    }

    public function testLoadDeclaresItsConcreteReturnType(): void
    {
        $returnType = (new \ReflectionMethod(PhpLoader::class, 'load'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(LoadResult::class, $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }
}
