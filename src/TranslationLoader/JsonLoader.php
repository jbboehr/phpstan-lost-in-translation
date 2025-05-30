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

use JsonStreamingParser\Parser;
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

        try {
            $lineNumbers = $this->buildLineNumberMap($file);
        } catch (\Throwable $e) {
            $warnings[] = [
                sprintf('Failed to get line numbers for JSON file: %s', $e->getMessage()),
                $file->getPathname(),
                -1,
            ];
            $lineNumbers = [];
        }

        $results = [];

        foreach ($raw as $k => $v) {
            $line = $lineNumbers[$k] ?? $lineNumbers["int\0" . $k] ?? -1;

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
        }

        return new LoadResult($results, $lineNumbers, $warnings);
    }

    /**
     * @return array<string, int>
     */
    private function buildLineNumberMap(SplFileInfo $file): array
    {
        $fh = fopen($file->getPathname(), 'r');
        if (false === $fh) {
            return [];
        }

        $listener = new StreamingJsonListener();
        $parser = new Parser($fh, $listener);
        $parser->parse();
        return $listener->getLocations();
    }
}
