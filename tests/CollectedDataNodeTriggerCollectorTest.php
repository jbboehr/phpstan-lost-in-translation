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

namespace jbboehr\PHPStanLostInTranslation\Tests;

use jbboehr\PHPStanLostInTranslation\CollectedDataNodeTriggerCollector;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Testing\PHPStanTestCase;

final class CollectedDataNodeTriggerCollectorTest extends PHPStanTestCase
{
    public function testDisabledEndOfAnalysisFeaturesDoNotCreateCollectedData(): void
    {
        $collector = self::getContainer()->getByType(CollectedDataNodeTriggerCollector::class);

        /** @phpstan-ignore-next-line phpstanApi.constructor */
        $node = new FileNode([]);

        self::assertNull($collector->processNode(
            $node,
            $this->createStub(Scope::class),
        ));
    }

    public static function getAdditionalConfigFiles(): array
    {
        return array_merge(parent::getAdditionalConfigFiles(), [
            __DIR__ . '/configuration-end-analysis-disabled.neon',
        ]);
    }
}
