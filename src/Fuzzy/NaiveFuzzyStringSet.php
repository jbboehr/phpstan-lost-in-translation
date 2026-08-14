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

final class NaiveFuzzyStringSet implements FuzzyStringSetInterface
{
    /** @var list<non-empty-string> */
    private array $strings = [];

    /** @var array<non-empty-string, true> */
    private array $stringSet = [];

    /**
     * @param ?list<non-empty-string> $strings
     */
    public function __construct(?array $strings = null)
    {
        $this->addMany($strings ?? []);
    }

    public function add(string $string): void
    {
        if (isset($this->stringSet[$string])) {
            return;
        }

        $this->strings[] = $string;
        $this->stringSet[$string] = true;
    }

    public function addMany(array $strings): void
    {
        foreach ($strings as $string) {
            $this->add($string);
        }
    }

    public function search(string $string): ?string
    {
        $stringWithSmallestDelta = null;
        $smallestDelta = null;

        foreach ($this->strings as $otherString) {
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
