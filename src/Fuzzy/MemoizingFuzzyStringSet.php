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

namespace jbboehr\PHPStanLostInTranslation\Fuzzy;

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
