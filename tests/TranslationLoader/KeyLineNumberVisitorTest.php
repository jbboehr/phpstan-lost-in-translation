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

namespace jbboehr\PHPStanLostInTranslation\Tests\TranslationLoader;

use jbboehr\PHPStanLostInTranslation\TranslationLoader\KeyLineNumberVisitor;
use PhpParser\NodeTraverser;
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
        $statements = (new ParserFactory())->createForHostVersion()->parse($source);
        self::assertNotNull($statements);

        $visitor = new KeyLineNumberVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($statements);

        return $visitor->getLineNumbers();
    }
}
