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

use Fuse\Fuse;

final class FuseFuzzyStringSet implements FuzzyStringSetInterface
{
    private readonly Fuse $fuse;

    /**
     * @param ?list<non-empty-string> $strings
     */
    public function __construct(?array $strings = null)
    {
        $this->fuse = new Fuse($strings ?? [], [
            'isCaseSensitive' => true,
            'includeScore' => true,
            'minMatchCharLength' => 2,
            'shouldSort' => true,
            'threshold' => 0.25,
        ]);
    }

    public function add(string $string): void
    {
        $this->fuse->add($string);
    }

    public function addMany(array $strings): void
    {
        foreach ($strings as $string) {
            $this->fuse->add($string);
        }
    }

    public function search(string $string): ?string
    {
        $result = $this->fuse->search($string)[0]['item'] ?? null;

        if (null !== $result) {
            $ratio = levenshtein($string, $result) / strlen($string);

            if ($ratio > self::THRESHOLD) {
                return null;
            }
        }

        return $result;
    }
}
