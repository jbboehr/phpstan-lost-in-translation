<?php
/**
 * Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\TranslationCall;
use PHPStan\Type\Constant\ConstantStringType;

final class TranslationCallTest extends \PHPUnit\Framework\TestCase
{
    public function testSerialization(): void
    {
        $call = new TranslationCall(
            null,
            'foo',
            'bar',
            1,
            [],
            new ConstantStringType('baz'),
        );

        /** @phpstan-ignore-next-line argument.type */
        $this->assertEquals($call, TranslationCall::fromJsonArray(json_decode(json_encode($call), true)));
    }

    public function testInvalidSerializationWithInvalidArray(): void
    {
        $this->expectException(\DomainException::class);

        TranslationCall::fromJsonArray([]);
    }

    public function testInvalidSerializationWithInvalidClass(): void
    {
        $this->expectException(\DomainException::class);

        TranslationCall::fromJsonArray([TranslationCall::class => serialize(new \stdClass())]);
    }
}
