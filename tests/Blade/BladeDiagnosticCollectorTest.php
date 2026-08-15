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

namespace jbboehr\PHPStanLostInTranslation\Tests\Blade;

use jbboehr\PHPStanLostInTranslation\Blade\BladeDiagnosticCollector;
use jbboehr\PHPStanLostInTranslation\Utils;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;

final class BladeDiagnosticCollectorTest extends \PHPUnit\Framework\TestCase
{
    public function testSharesStructuredDiagnosticsAcrossBladeStanContainers(): void
    {
        $tip = Utils::formatTipForKeyValue('en', 'messages.example', 'Example');
        $error = RuleErrorBuilder::message('Unused translation replacement: "unused"')
            ->identifier('lostInTranslation.invalidReplacement.unused')
            ->metadata(Utils::metadata(key: 'messages.example', locale: 'en', value: 'Example'))
            ->addTip($tip)
            ->file(__DIR__ . '/../data/example-blade-compiled.php')
            ->line(4)
            ->build();

        $collector = new BladeDiagnosticCollector();

        $this->assertTrue($collector->push(
            [$error],
            __DIR__ . '/../data/example-blade-compiled.php',
            4,
        ));

        $compiledScope = $this->createStub(Scope::class);
        $compiledScope->method('getFile')->willReturn('/tmp/098f6bcd4621d373cade4e832627b4f6-blade-compiled.php');
        $this->assertNull($collector->processNode($this->createStub(FuncCall::class), $compiledScope));

        $outerNode = $this->createStub(FuncCall::class);
        $outerNode->method('getStartLine')->willReturn(215);
        $outerScope = $this->createStub(Scope::class);
        $outerScope->method('getFile')->willReturn('/app/Http/Controllers/BookController.php');

        $this->assertSame([
            [
                'message' => 'Unused translation replacement: "unused"',
                'identifier' => 'lostInTranslation.invalidReplacement.unused',
                'metadata' => [
                    'lostInTranslation::key' => 'messages.example',
                    'lostInTranslation::locale' => 'en',
                    'lostInTranslation::value' => 'Example',
                    'template_file_path' => 'resources/views/example.blade.php',
                    'template_line' => 19,
                ],
                'tip' => $tip,
                'file' => '/app/Http/Controllers/BookController.php',
                'line' => 215,
            ],
        ], (new BladeDiagnosticCollector())->processNode($outerNode, $outerScope));
    }

    public function testLeavesErrorsForBladeStanWhenNoTemplateMarkerExists(): void
    {
        $error = RuleErrorBuilder::message('Example')
            ->identifier('lostInTranslation.example')
            ->build();

        $this->assertFalse((new BladeDiagnosticCollector())->push([$error], __FILE__, __LINE__));
    }

    public function testQueuesDiagnosticsFromMultipleNestedAnalyses(): void
    {
        $firstError = RuleErrorBuilder::message('First diagnostic')
            ->identifier('lostInTranslation.first')
            ->build();
        $secondError = RuleErrorBuilder::message('Second diagnostic')
            ->identifier('lostInTranslation.second')
            ->build();
        $collector = new BladeDiagnosticCollector();

        $this->assertTrue($collector->push(
            [$firstError],
            __DIR__ . '/../data/example-blade-compiled.php',
            4,
        ));
        $this->assertTrue($collector->push(
            [$secondError],
            __DIR__ . '/../data/example-blade-compiled.php',
            4,
        ));

        $outerNode = $this->createStub(FuncCall::class);
        $outerNode->method('getStartLine')->willReturn(10);
        $outerScope = $this->createStub(Scope::class);
        $outerScope->method('getFile')->willReturn('/app/Http/Controllers/BookController.php');

        $diagnostics = $collector->processNode($outerNode, $outerScope);

        $this->assertIsArray($diagnostics);
        $this->assertSame(
            ['First diagnostic', 'Second diagnostic'],
            array_column($diagnostics, 'message'),
        );
    }

    public function testDeduplicatesIdenticalDiagnosticsFromRepeatedNestedAnalysis(): void
    {
        $error = RuleErrorBuilder::message('Repeated diagnostic')
            ->identifier('lostInTranslation.repeated')
            ->metadata(['example' => 'value'])
            ->addTip('Repeated tip')
            ->build();
        $collector = new BladeDiagnosticCollector();

        $this->assertTrue($collector->push(
            [$error],
            __DIR__ . '/../data/example-blade-compiled.php',
            4,
        ));
        $this->assertTrue($collector->push(
            [$error],
            __DIR__ . '/../data/example-blade-compiled.php',
            4,
        ));

        $outerNode = $this->createStub(FuncCall::class);
        $outerNode->method('getStartLine')->willReturn(10);
        $outerScope = $this->createStub(Scope::class);
        $outerScope->method('getFile')->willReturn('/app/Http/Controllers/BookController.php');

        $diagnostics = $collector->processNode($outerNode, $outerScope);

        $this->assertIsArray($diagnostics);
        $this->assertCount(1, $diagnostics);
        $this->assertSame('Repeated diagnostic', $diagnostics[0]['message']);
    }

    public function testUsesTheNearestPrecedingTemplateMarker(): void
    {
        $compiledFile = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8)) . '.php';
        $error = RuleErrorBuilder::message('Example')
            ->identifier('lostInTranslation.example')
            ->build();
        $collector = new BladeDiagnosticCollector();

        try {
            $this->assertNotFalse(file_put_contents(
                $compiledFile,
                "/** file: resources/views/first.blade.php, line: 11 */\n"
                . "__('first');\n"
                . "/** file: resources/views/second.blade.php, line: 22 */\n"
                . "__('second');\n",
            ));
            $this->assertTrue($collector->push([$error], $compiledFile, 2));
            $this->assertTrue($collector->push([$error], $compiledFile, 4));

            $outerNode = $this->createStub(FuncCall::class);
            $outerNode->method('getStartLine')->willReturn(10);
            $outerScope = $this->createStub(Scope::class);
            $outerScope->method('getFile')->willReturn('/app/Http/Controllers/BookController.php');

            $diagnostics = $collector->processNode($outerNode, $outerScope);

            $this->assertIsArray($diagnostics);
            $this->assertSame(
                ['resources/views/first.blade.php', 'resources/views/second.blade.php'],
                array_column(array_column($diagnostics, 'metadata'), 'template_file_path'),
            );
            $this->assertSame(
                [11, 22],
                array_column(array_column($diagnostics, 'metadata'), 'template_line'),
            );
        } finally {
            if (file_exists($compiledFile)) {
                unlink($compiledFile);
            }
        }
    }

    public function testRejectsAnEmptyTemplatePath(): void
    {
        $compiledFile = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8)) . '.php';
        $error = RuleErrorBuilder::message('Example')
            ->identifier('lostInTranslation.example')
            ->build();

        try {
            $this->assertNotFalse(file_put_contents($compiledFile, "/** file: , line: 19 */\n__('example');\n"));
            $this->assertFalse((new BladeDiagnosticCollector())->push([$error], $compiledFile, 2));
        } finally {
            if (file_exists($compiledFile)) {
                unlink($compiledFile);
            }
        }
    }

    public function testClearsCompiledFileCacheAfterTheOuterCall(): void
    {
        $compiledFile = sys_get_temp_dir() . '/phpstan-lost-in-translation-' . bin2hex(random_bytes(8)) . '.php';
        $error = RuleErrorBuilder::message('Example')
            ->identifier('lostInTranslation.example')
            ->build();
        $collector = new BladeDiagnosticCollector();

        $outerNode = $this->createStub(FuncCall::class);
        $outerNode->method('getStartLine')->willReturn(10);
        $outerScope = $this->createStub(Scope::class);
        $outerScope->method('getFile')->willReturn('/app/Http/Controllers/BookController.php');

        try {
            $this->assertNotFalse(file_put_contents(
                $compiledFile,
                "/** file: resources/views/first.blade.php, line: 19 */\n__('first');\n",
            ));
            $this->assertTrue($collector->push([$error], $compiledFile, 2));

            $firstDiagnostics = $collector->processNode($outerNode, $outerScope);
            $this->assertIsArray($firstDiagnostics);
            $this->assertSame('/app/Http/Controllers/BookController.php', $firstDiagnostics[0]['file']);
            $this->assertSame(10, $firstDiagnostics[0]['line']);
            $this->assertSame(19, $firstDiagnostics[0]['metadata']['template_line']);

            $this->assertNotFalse(file_put_contents(
                $compiledFile,
                "/** file: resources/views/second.blade.php, line: 37 */\n__('second');\n",
            ));
            $this->assertTrue($collector->push([$error], $compiledFile, 2));

            $secondOuterNode = $this->createStub(FuncCall::class);
            $secondOuterNode->method('getStartLine')->willReturn(24);
            $secondOuterScope = $this->createStub(Scope::class);
            $secondOuterScope->method('getFile')->willReturn('/app/Http/Controllers/PageController.php');

            $secondDiagnostics = $collector->processNode($secondOuterNode, $secondOuterScope);
            $this->assertIsArray($secondDiagnostics);
            $this->assertSame('/app/Http/Controllers/PageController.php', $secondDiagnostics[0]['file']);
            $this->assertSame(24, $secondDiagnostics[0]['line']);
            $this->assertSame(37, $secondDiagnostics[0]['metadata']['template_line']);
        } finally {
            if (file_exists($compiledFile)) {
                unlink($compiledFile);
            }
        }
    }
}
