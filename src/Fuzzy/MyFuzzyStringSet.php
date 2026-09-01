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
 * @note sadly, turns out this is worse than a naive search
 */
final class MyFuzzyStringSet implements FuzzyStringSetInterface
{
    // phpcs:ignore
    private const EMPTY_ARRAY = [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0];

    /**
     * @var array<int, array<int, int>>
     */
    private array $index = [];

    /** @var array<int, non-empty-string> */
    private array $strings = [];

    /** @var array<non-empty-string, int> */
    private array $stringToIndex = [];

    /**
     * @param ?list<non-empty-string> $strings
     */
    public function __construct(?array $strings = null)
    {
        $this->index = array_fill(0, 256, []);

        if ($strings !== null) {
            $this->addMany($strings);
        }
    }

    public function add(string $string): void
    {
        $this->addMany([$string]);
    }

    /**
     * @param list<non-empty-string> $strings
     */
    public function addMany(array $strings): void
    {
        foreach ($strings as $string) {
            if (isset($this->stringToIndex[$string])) {
                continue;
            }

            $index = count($this->strings);

            $this->strings[] = $string;
            $this->stringToIndex[$string] = $index;

            $vector = self::vec($string);

            foreach ($vector as $byte => $count) {
                if ($count > 0) {
                    $this->index[$byte][$index] = $count;
                }
            }
        }
    }

    public function search(string $string): ?string
    {
        $vector = self::vec($string);
        $length = strlen($string);
        $threshold = $length;

        $otherIndexDeltas = [];

        foreach ($vector as $byte => $count) {
            foreach ($this->index[$byte] as $otherStringIndex => $otherCount) {
                $currentDelta = $otherIndexDeltas[$otherStringIndex] ?? 0;

                if ($currentDelta === false) {
                    continue;
                }

                $currentDelta += abs($count - $otherCount);

                if ($currentDelta > $threshold) {
                    $currentDelta = false;
                }

                $otherIndexDeltas[$otherStringIndex] = $currentDelta;
            }
        }

        // convert to levenshtein and filter
        for ($i = 0; $i < count($this->strings); $i++) {
            if (isset($otherIndexDeltas[$i])) {
                if ($otherIndexDeltas[$i] === false) {
                    unset($otherIndexDeltas[$i]);
                    continue;
                }

                $delta = levenshtein($string, $this->strings[$i]);

                if ($delta > $threshold) {
                    unset($otherIndexDeltas[$i]);
                    continue;
                }

                $otherIndexDeltas[$i] = $delta;
            }
        }

        if ([] === $otherIndexDeltas) {
            return null;
        }

        asort($otherIndexDeltas);
        $smallestDeltaIndex = array_key_first($otherIndexDeltas);
        $smallestDelta = $otherIndexDeltas[$smallestDeltaIndex];
        $result = $this->strings[$smallestDeltaIndex] ?? null;

        if ($result === null || $smallestDelta === false) {
            return null;
        }

        $ratio = $smallestDelta / strlen($string);

        if ($ratio > self::THRESHOLD) {
            return null;
        }

        return $result;
    }

    /**
     * @param string $string
     * @return array<int, int>
     */
    private static function vec(string $string): array
    {
        $arr = self::EMPTY_ARRAY;

        for ($i = 0, $l = strlen($string); $i < $l; $i++) {
            $c = ord($string[$i]);
            $arr[$c]++;
        }

        return $arr;
    }
}
