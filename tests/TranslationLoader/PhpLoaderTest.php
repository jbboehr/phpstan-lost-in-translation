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

use jbboehr\PHPStanLostInTranslation\TranslationLoader\LoadResult;
use jbboehr\PHPStanLostInTranslation\TranslationLoader\PhpLoader;
use PHPUnit\Framework\TestCase;

final class PhpLoaderTest extends TestCase
{
    public function testLoadDeclaresItsConcreteReturnType(): void
    {
        $returnType = (new \ReflectionMethod(PhpLoader::class, 'load'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame(LoadResult::class, $returnType->getName());
        $this->assertFalse($returnType->allowsNull());
    }
}
