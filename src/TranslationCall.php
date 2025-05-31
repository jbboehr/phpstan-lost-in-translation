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

use PHPStan\Type\Type;

/**
 * @phpstan-import-type PossibleTranslationRecordCollection from LostInTranslationHelper
 */
final class TranslationCall implements \JsonSerializable
{
    /**
     * @phpstan-param PossibleTranslationRecordCollection $possibleTranslations
     */
    public function __construct(
        public readonly ?string $className,
        public readonly string $functionName,
        public readonly string $file,
        public readonly int $line,
        public readonly array $possibleTranslations,
        public readonly Type $keyType,
        public readonly ?Type $replaceType = null,
        public readonly ?Type $localeType = null,
        public readonly ?Type $numberType = null,
        public readonly bool $isChoice = false,
    ) {
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            self::class => serialize($this),
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

        $call = unserialize($buffer);

        if (!($call instanceof self)) {
            throw new \DomainException();
        }

        return $call;
    }
}
