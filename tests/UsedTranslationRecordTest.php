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

use jbboehr\PHPStanLostInTranslation\UsedTranslationRecord;

final class UsedTranslationRecordTest extends \PHPUnit\Framework\TestCase
{
    public function testSerialization(): void
    {
        $call = new UsedTranslationRecord(
            key: 'foo',
            locale: 'bar',
            file: 'baz',
            line: 69420,
        );

        /** @phpstan-ignore-next-line argument.type */
        $this->assertEquals($call, UsedTranslationRecord::fromJsonArray(json_decode(json_encode($call), true)));
    }

    public function testInvalidSerializationWithInvalidArray(): void
    {
        $this->expectException(\DomainException::class);

        UsedTranslationRecord::fromJsonArray([]);
    }

    public function testInvalidSerializationWithInvalidClass(): void
    {
        $this->expectException(\DomainException::class);

        UsedTranslationRecord::fromJsonArray([
            UsedTranslationRecord::class => base64_encode(serialize(new \stdClass())),
        ]);
    }
}
