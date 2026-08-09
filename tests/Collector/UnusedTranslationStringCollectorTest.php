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

namespace jbboehr\PHPStanLostInTranslation\Tests\Collector;

use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use jbboehr\PHPStanLostInTranslation\TranslationCall;
use jbboehr\PHPStanLostInTranslation\UnusedTranslationStringCollector;
use jbboehr\PHPStanLostInTranslation\UsedTranslationRecord;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantStringType;

final class UnusedTranslationStringCollectorTest extends \PHPUnit\Framework\TestCase
{
    public function testSharesQueuedRecordsAcrossInstances(): void
    {
        $helper = $this->createStub(LostInTranslationHelper::class);
        $scope = $this->createMock(Scope::class);
        $scope->method('getFile')
            ->willReturn('/tmp/example.php');
        $node = $this->createStub(FuncCall::class);

        $collector = new UnusedTranslationStringCollector($helper);
        $collector->processNode($node, $scope);
        $collector->push(new TranslationCall(
            null,
            '__',
            '/tmp/example.blade.php',
            1,
            [],
            new ConstantStringType('messages.example'),
        ));

        $otherCollector = new UnusedTranslationStringCollector($helper);

        $this->assertEquals([
            new UsedTranslationRecord(
                key: 'messages.example',
                locale: '*',
                file: '/tmp/example.blade.php',
                line: 1,
            ),
        ], $otherCollector->processNode($node, $scope));
    }

    public function testExceptionConversion(): void
    {
        if (!class_exists(FuncCall::class)) {
            $this->markTestIncomplete('This seems to fail when you filter, probably PHPStan autoload does not get initialized');
        }

        $ex = new \RuntimeException(self::class);
        $node = $this->createStub(FuncCall::class);

        $helper = $this->createMock(LostInTranslationHelper::class);
        $helper->method('parseCallLike')
            ->willThrowException($ex);

        $obj = new UnusedTranslationStringCollector($helper);

        $this->expectException(ShouldNotHappenException::class);
        $this->expectExceptionMessage('phpstan-lost-in-translation');

        $obj->processNode(
            $node,
            $this->createStub(Scope::class),
        );
    }
}
