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

final class NullFuzzyStringSet implements FuzzyStringSetInterface
{
    /**
     * @param ?list<non-empty-string> $strings
     * @phpstan-ignore constructor.unusedParameter
     */
    public function __construct(?array $strings = null)
    {
    }

    public function add(string $string): void
    {
    }

    public function addMany(array $strings): void
    {
    }

    public function search(string $string): ?string
    {
        return null;
    }
}
