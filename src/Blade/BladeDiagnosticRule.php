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

namespace jbboehr\PHPStanLostInTranslation\Blade;

use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<CollectedDataNode>
 * @phpstan-import-type CollectedDiagnostic from BladeDiagnosticCollector
 */
final class BladeDiagnosticRule implements Rule
{
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            /** @var array<string, list<list<CollectedDiagnostic>>> $data */
            $data = $node->get(BladeDiagnosticCollector::class);

            /** @var list<IdentifierRuleError> $errors */
            $errors = [];

            foreach ($data as $fileResults) {
                foreach ($fileResults as $diagnostics) {
                    foreach ($diagnostics as $diagnostic) {
                        $builder = RuleErrorBuilder::message($diagnostic['message'])
                            ->identifier($diagnostic['identifier'])
                            ->metadata($diagnostic['metadata'])
                            ->file($diagnostic['file'])
                            ->line($diagnostic['line']);

                        if (null !== $diagnostic['tip']) {
                            $builder->addTip($diagnostic['tip']);
                        }

                        $errors[] = $builder->build();
                    }
                }
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
