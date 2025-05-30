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

use JsonStreamingParser\Listener\ListenerInterface;
use JsonStreamingParser\Listener\PositionAwareInterface;

class StreamingJsonListener implements ListenerInterface, PositionAwareInterface
{
    private int $lineNumber = -1;

    /** @var array<string, int> */
    private array $locations = [];

    /** @phpstan-var list<array{self::*, array{}}> */
    private array $stack = [];

    private int $lastArrayIndex = -1;

    private const ARRAY = 1;
    private const OBJECT = 2;
    private const DOCUMENT = 3;

    /**
     * @return array<string, int>
     */
    public function getLocations(): array
    {
        return $this->locations;
    }

    public function startDocument(): void
    {
        $this->stack[] = [self::DOCUMENT, []];
    }

    public function endDocument(): void
    {
        array_pop($this->stack);
    }

    public function startObject(): void
    {
        $this->stack[] = [self::OBJECT, []];
    }

    public function endObject(): void
    {
        array_pop($this->stack);
    }

    public function startArray(): void
    {
        $this->stack[] = [self::ARRAY, []];
        $this->lastArrayIndex = -1;
    }

    public function endArray(): void
    {
        array_pop($this->stack);
    }

    public function key(string $key): void
    {
        if (count($this->stack) !== 2) {
            // only allow one deep
            return;
        }

        switch ($this->stack[count($this->stack) - 1][0]) {
            case self::OBJECT:
                $this->locations[$key] = $this->lineNumber;
                break;
        }
    }

    public function value($value): void
    {
        if (count($this->stack) !== 2) {
            // only allow one deep
            return;
        }

        switch ($this->stack[count($this->stack) - 1][0]) {
            case self::ARRAY:
                $index = ++$this->lastArrayIndex;
                $this->locations["int\0" . $index] = $this->lineNumber;
                break;
        }
    }

    public function whitespace(string $whitespace): void
    {
    }

    public function setFilePosition(int $lineNumber, int $charNumber): void
    {
        $this->lineNumber = $lineNumber;
    }
}
