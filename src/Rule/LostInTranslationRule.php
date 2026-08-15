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

namespace jbboehr\PHPStanLostInTranslation\Rule;

use jbboehr\PHPStanLostInTranslation\Blade\BladeDiagnosticCollector;
use jbboehr\PHPStanLostInTranslation\CallRule\CallRuleCollection;
use jbboehr\PHPStanLostInTranslation\LostInTranslationHelper;
use jbboehr\PHPStanLostInTranslation\ShouldNotHappenException;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Node\Expr\CallLike>
 */
final class LostInTranslationRule implements Rule
{
    public function __construct(
        private readonly LostInTranslationHelper $helper,
        private readonly CallRuleCollection $rules,
        private readonly BladeDiagnosticCollector $bladeDiagnosticCollector = new BladeDiagnosticCollector(),
        private readonly bool $bridgeBladeDiagnostics = false,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        try {
            $errors = [];
            $call = $this->helper->parseCallLike($node, $scope);

            if ($call !== null) {
                foreach ($this->rules as $rule) {
                    $errors = array_merge(
                        $errors,
                        $rule->processCall($call),
                    );
                }
            }

            if (
                $this->bridgeBladeDiagnostics
                && [] !== $errors
                && 1 === preg_match(BladeDiagnosticCollector::COMPILED_FILE_PATTERN, $scope->getFile())
                && $this->bladeDiagnosticCollector->push($errors, $scope->getFile(), $node->getStartLine())
            ) {
                return [];
            }

            return $errors;
        } catch (\Throwable $e) {
            ShouldNotHappenException::rethrow($e);
        }
    }
}
