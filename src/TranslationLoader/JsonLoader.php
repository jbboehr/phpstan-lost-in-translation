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

use Symfony\Component\Finder\SplFileInfo;

final class JsonLoader
{
    public function load(SplFileInfo $file): LoadResult
    {
        $warnings = [];
        $buffer = $file->getContents();
        try {
            $raw = json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $warnings[] = [
                sprintf('Failed to parse JSON file: %s', $e->getMessage()),
                $file->getPathname(),
                -1,
            ];
            return new LoadResult([], [], $warnings);
        }

        if (!is_array($raw)) {
            $warnings[] = [
                sprintf('Invalid data type: "%s"', gettype($raw)),
                $file->getPathname(),
                -1,
            ];
            return new LoadResult([], [], $warnings);
        }

        $results = [];
        $lineNumbers = [];

        foreach ($raw as $k => $v) {
            // this is gross but will probably work most of the time
            try {
                $encoded = json_encode($k, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (false !== ($pos = strpos($buffer, $encoded))) {
                $line = 1 + substr_count($buffer, "\n", 0, $pos);
            } else {
                $line = -1;
            }

            if (!is_string($k)) {
                $warnings[] = [
                    sprintf("Invalid key: %s", json_encode($k, JSON_THROW_ON_ERROR)),
                    $file->getPathname(),
                    $line,
                ];
                continue;
            }

            if (!is_string($v)) {
                $warnings[] = [
                    sprintf("Invalid value: %s", json_encode($v, JSON_THROW_ON_ERROR)),
                    $file->getPathname(),
                    $line,
                ];
                continue;
            }

            $results[$k] = $v;
            $lineNumbers[$k] = $line;
        }

        return new LoadResult($results, $lineNumbers, $warnings);
    }
}
