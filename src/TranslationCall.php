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

    public function jsonSerialize(): array
    {
        return [
            'className' => $this->className,
            'functionName' => $this->functionName,
            'file' => $this->file,
            'line' => $this->line,
            'possibleTranslations' => $this->possibleTranslations,
            'keyType' => $this->keyType !== null ? serialize($this->keyType) : null,
            'replaceType' => $this->replaceType !== null ? serialize($this->replaceType) : null,
            'localeType' => $this->localeType !== null ? serialize($this->localeType) : null,
            'numberType' => $this->numberType !== null ? serialize($this->numberType) : null,
            'isChoice' => $this->isChoice,
        ];
    }

    public static function fromJsonArray(array $json): self
    {
        return new self(
            className: $json['className'] ?? null,
            functionName: $json['functionName'] ?? throw new \DomainException(),
            file: $json['file'] ?? throw new \DomainException(),
            line: $json['line'] ?? throw new \DomainException(),
            possibleTranslations: $json['possibleTranslations'] ?? throw new \DomainException(),
            keyType: unserialize($json['keyType'] ?? throw new \DomainException()),
            replaceType: isset($json['replaceType']) ? unserialize($json['replaceType']) : null,
            localeType: isset($json['localeType']) ? unserialize($json['localeType']) : null,
            numberType: isset($json['numberType']) ? unserialize($json['numberType']) : null,
            isChoice: $json['isChoice'] ?? throw new \DomainException(),
        );
    }
}
