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

namespace jbboehr\PHPStanLostInTranslation\TranslationLoader;

use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\NodeVisitorAbstract;

final class KeyLineNumberVisitor extends NodeVisitorAbstract
{
    /** @var array<non-empty-string, int> */
    private array $lineNumbers = [];

    /** @var array<non-empty-string, int> */
    private array $lineNumberDepths = [];

    /** @var array<non-empty-string, int> */
    private array $rawLineNumbers = [];

    /** @var list<int|string> */
    private array $stack = [];

    /**
     * @return null
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Expr\ArrayItem) {
            if ($node->key instanceof Scalar) {
                $this->stack[] = self::getScalarKeyValue($node->key) ?? 'unknown';
            } else {
                // Can't really handle lists here unfortunately
                $this->stack[] = 'unknown';
            }
        }

        return null;
    }

    /**
     * @return null
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\ArrayItem) {
            $path = join('.', $this->stack);
            $depth = count($this->stack);
            $rawPath = serialize($this->stack);
            assert('' !== $rawPath);
            $this->rawLineNumbers[$rawPath] = $node->getStartLine();

            $addressable = true;

            if (1 < $depth) {
                foreach ($this->stack as $key) {
                    if (is_string($key) && str_contains($key, '.')) {
                        $addressable = false;
                        break;
                    }
                }
            }

            if (
                $addressable
                && strlen($path) > 0
                && (!isset($this->lineNumberDepths[$path]) || $depth <= $this->lineNumberDepths[$path])
            ) {
                $this->lineNumbers[$path] = $node->getStartLine();
                $this->lineNumberDepths[$path] = $depth;
            }
            array_pop($this->stack);
        }

        return null;
    }

    /**
     * @return array<non-empty-string, int>
     */
    public function getLineNumbers(bool $raw = false): array
    {
        return $raw ? $this->rawLineNumbers : $this->lineNumbers;
    }

    private static function getScalarKeyValue(Scalar $key): int|string|null
    {
        // PHP-Parser 4 and 5 use different integer node classes, but both
        // expose scalar values through NodeAbstract::jsonSerialize().
        $value = $key->jsonSerialize()['value'] ?? null;

        return is_int($value) || is_string($value) ? $value : null;
    }
}
