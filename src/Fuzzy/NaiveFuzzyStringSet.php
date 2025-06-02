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

final class NaiveFuzzyStringSet implements FuzzyStringSetInterface
{
    /** @var array<string, true> */
    private array $strings;

    /**
     * @param ?list<string> $strings
     */
    public function __construct(?array $strings = null)
    {
        $this->addMany($strings ?? []);
    }

    public function add(string $string): void
    {
        $this->strings[$string] = true;
    }

    public function addMany(array $strings): void
    {
        foreach ($strings as $string) {
            $this->strings[$string] = true;
        }
    }

    public function search(string $string): ?string
    {
        $stringWithSmallestDelta = null;
        $smallestDelta = null;

        foreach ($this->strings as $otherString => $unused) {
            $delta = levenshtein($string, $otherString);

            if ($smallestDelta === null || $smallestDelta > $delta) {
                $stringWithSmallestDelta = $otherString;
                $smallestDelta = $delta;
            }
        }

        if ($smallestDelta === null) {
            return null;
        }

        $ratio = $smallestDelta / strlen($string);

        if ($ratio > self::THRESHOLD) {
            return null;
        }

        return $stringWithSmallestDelta;
    }
}
