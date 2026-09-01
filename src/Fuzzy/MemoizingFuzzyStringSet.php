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

namespace jbboehr\PHPStanLostInTranslation\Fuzzy;

/**
 * @internal
 */
final class MemoizingFuzzyStringSet implements FuzzyStringSetInterface
{
    /**
     * @var array<non-empty-string, ?non-empty-string>
     */
    private array $memo = [];

    public function __construct(
        private readonly FuzzyStringSetInterface $inner,
    ) {
    }

    public function add(string $string): void
    {
        $this->memo = [];

        $this->inner->add($string);
    }

    public function addMany(array $strings): void
    {
        $this->memo = [];

        $this->inner->addMany($strings);
    }

    public function search(string $string): ?string
    {
        if (array_key_exists($string, $this->memo)) {
            return $this->memo[$string];
        }

        return $this->memo[$string] = $this->inner->search($string);
    }
}
