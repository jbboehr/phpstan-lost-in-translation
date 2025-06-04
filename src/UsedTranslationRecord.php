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

namespace jbboehr\PHPStanLostInTranslation;

final class UsedTranslationRecord implements \JsonSerializable
{
    /**
     * @param non-empty-string $file
     */
    public function __construct(
        public readonly string $key,
        public readonly string $locale,
        public readonly string $file,
        public readonly int $line,
    ) {
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            // Keys with binary data fail to serialize when transferred from the phpstan worker
            self::class => base64_encode(serialize($this)),
        ];
    }

    /**
     * @param array<array-key, mixed> $json
     */
    public static function fromJsonArray(array $json): self
    {
        $buffer = $json[self::class] ?? null;

        if (!is_string($buffer)) {
            throw new \DomainException();
        }

        // Keys with binary data fail to serialize when transferred from the phpstan worker
        $call = unserialize(base64_decode($buffer));

        if (!($call instanceof self)) {
            throw new \DomainException();
        }

        return $call;
    }

    /**
     * @return array{key: string, locale: string, file: non-empty-string, line: int}
     */
    public function toArray(): array
    {
        return (array) $this;
    }
}
