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
use PhpParser\NodeVisitorAbstract;

final class KeyLineNumberVisitor extends NodeVisitorAbstract
{
    /** @var array<string, int> */
    private array $lineNumbers = [];

    public function enterNode(Node $node)
    {
        if (!($node instanceof Node\Expr\Array_)) {
            return null;
        }

        foreach ($node->items as $k => $v) {
            if ($v->key instanceof Node\Scalar\String_) {
                $this->lineNumbers[$v->key->value] = $v->key->getStartLine();
            }
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
