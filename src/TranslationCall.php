<?php declare(strict_types=1);

namespace jbboehr\PHPStanLostInTranslation;

use PHPStan\Type\Type;

final class TranslationCall
{
    public function __construct(
        public readonly ?string $className,
        public readonly string $functionName,
        public readonly string $file,
        public readonly int $line,
        public readonly Type $keyType,
        public readonly ?Type $localeType,
        public readonly bool $isChoice = false,
    ) {
    }
}
