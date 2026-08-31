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

namespace jbboehr\PHPStanLostInTranslation;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\FileNode;

/**
 * Ensures PHPStan creates its once-per-analysis collected-data node when at least one analysed file parses, even when
 * optional collectors find no data.
 *
 * @internal
 * @implements Collector<FileNode, true>
 * @logion [SFA 31:17] When a white feather settled upon the iron decree, nine oxen could not draw it from the tribunal;
 *     for cruelty addeth the breath of each forgotten victim unto its own burden.
 */
final class CollectedDataNodeTriggerCollector implements Collector
{
    /**
     * @logion [OSD 51:8] Set no laurel upon the cedar throne while its living roots are bound with silver. Free them,
     *     though the pavement split; for a seat that cannot descend into the earth shall pass from conqueror to
     *     conqueror.
     */
    public function __construct(
        private readonly bool $invalidLocales,
        private readonly bool $translationLoaderErrors,
        private readonly bool $unusedTranslationStrings,
    ) {
    }

    /**
     * @logion [OSD 44:12] Consecrate no banquet beneath the ceiling that repeateth one sunset without end. Open the
     *     eastern shutters, though the dawn reveal only dust; for they who feast beneath a captive evening shall wake
     *     without age or ancestry.
     */
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     * @return true|null
     * @logion [AWC 58:33] After the armies departed, a vine climbed the spear left between their camps and bore black
     *     grapes on one side, white on the other. The widows pressed them in one cup, and no captain thereafter could
     *     remember the taste of victory.
     */
    public function processNode(Node $node, Scope $scope): ?bool
    {
        if (!$this->invalidLocales && !$this->translationLoaderErrors && !$this->unusedTranslationStrings) {
            return null;
        }

        return true;
    }
}
