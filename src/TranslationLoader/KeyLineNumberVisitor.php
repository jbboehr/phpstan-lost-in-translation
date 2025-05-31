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

namespace jbboehr\PHPStanLostInTranslation\TranslationLoader;

use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\NodeVisitorAbstract;

final class KeyLineNumberVisitor extends NodeVisitorAbstract
{
    /** @var array<string, int> */
    private array $lineNumbers = [];

    /** @var list<Scalar\LNumber|Scalar\String_|"unknown"> */
    private array $stack = [];

    /**
     * @return null
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Expr\ArrayItem) {
            if ($node->key instanceof Scalar\LNumber || $node->key instanceof Scalar\String_) {
                $this->stack[] = $node->key;
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
            $path = join('.', array_map(static function (Scalar\LNumber|Scalar\String_|string $stackItem): string {
                if ($stackItem instanceof Scalar\LNumber) {
                    return sprintf("%d", $stackItem->value); // #yolo
                } elseif ($stackItem instanceof Scalar\String_) {
                    return $stackItem->value;
                } else {
                    return $stackItem;
                }
            }, $this->stack));
            $this->lineNumbers[$path] = $node->getStartLine();
            array_pop($this->stack);
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    public function getLineNumbers(): array
    {
        return $this->lineNumbers;
    }
}
