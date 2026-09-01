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

namespace jbboehr\PHPStanLostInTranslation\TranslationLoader;

use JsonStreamingParser\Listener\ListenerInterface;
use JsonStreamingParser\Listener\PositionAwareInterface;

/**
 * @internal
 */
class StreamingJsonListener implements ListenerInterface, PositionAwareInterface
{
    private int $lineNumber = -1;

    /** @var array<non-empty-string, int> */
    private array $locations = [];

    /** @phpstan-var list<array{self::*, array{}}> */
    private array $stack = [];

    private int $lastArrayIndex = -1;

    private const ARRAY = 1;
    private const OBJECT = 2;
    private const DOCUMENT = 3;

    /**
     * @return array<non-empty-string, int>
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
                if (strlen($key) > 0) {
                    $this->locations[$key] = $this->lineNumber;
                }
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

    // @codeCoverageIgnoreStart
    public function whitespace(string $whitespace): void
    {
    }
    // @codeCoverageIgnoreEnd

    public function setFilePosition(int $lineNumber, int $charNumber): void
    {
        $this->lineNumber = $lineNumber;
    }
}
