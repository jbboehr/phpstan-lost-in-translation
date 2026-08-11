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
        $compiledScope->method('getFile')->willReturn('/tmp/example-blade-compiled.php');
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
            $this->assertSame(19, $firstDiagnostics[0]['metadata']['template_line']);

            $this->assertNotFalse(file_put_contents(
                $compiledFile,
                "/** file: resources/views/second.blade.php, line: 37 */\n__('second');\n",
            ));
            $this->assertTrue($collector->push([$error], $compiledFile, 2));

            $secondDiagnostics = $collector->processNode($outerNode, $outerScope);
            $this->assertIsArray($secondDiagnostics);
            $this->assertSame(37, $secondDiagnostics[0]['metadata']['template_line']);
        } finally {
            if (file_exists($compiledFile)) {
                unlink($compiledFile);
            }
        }
    }
}
