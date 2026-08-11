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

use jbboehr\PHPStanLostInTranslation\TranslationLoader\KeyLineNumberVisitor;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class KeyLineNumberVisitorTest extends TestCase
{
    public function testRecordsNestedStringAndIntegerKeys(): void
    {
        $this->assertSame(
            [
                'group.nested.7' => 5,
                'group.nested' => 4,
                'group' => 3,
            ],
            self::lineNumbers(<<<'PHP'
                <?php
                return [
                    'group' => [
                        'nested' => [
                            7 => 'value',
                        ],
                    ],
                ];
                PHP),
        );
    }

    public function testMarksUnsupportedScalarKeysAsUnknown(): void
    {
        $this->assertSame(
            ['unknown' => 3],
            self::lineNumbers(<<<'PHP'
                <?php
                return [
                    __LINE__ => 'value',
                ];
                PHP),
        );
    }

    /**
     * @return array<non-empty-string, int>
     */
    private static function lineNumbers(string $source): array
    {
        $parserFactory = new ParserFactory();
        $reflection = new \ReflectionObject($parserFactory);
        $create = $reflection->hasMethod('createForHostVersion')
            ? $reflection->getMethod('createForHostVersion')
            : $reflection->getMethod('create');
        $arguments = 'create' === $create->getName()
            ? [1] // ParserFactory::PREFER_PHP7 in PHP-Parser 4.
            : [];
        $parser = $create->invokeArgs($parserFactory, $arguments);
        self::assertInstanceOf(Parser::class, $parser);

        $statements = $parser->parse($source);
        self::assertNotNull($statements);

        $visitor = new KeyLineNumberVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($statements);

        return $visitor->getLineNumbers();
    }
}
