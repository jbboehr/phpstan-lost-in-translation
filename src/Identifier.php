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

/**
 * @note can't use constants here AFAIK
 * @phpstan-type MetadataType array<string, mixed>&array{
 *   "lostInTranslation::key"?: string,
 *   "lostInTranslation::locale"?: string,
 *   "lostInTranslation::value"?: string,
 *   "lostInTranslation::missingInLocales"?: list<string>,
 *   ...
 * }
 */
final class Identifier
{
    public const METADATA_KEY = 'lostInTranslation::key';
    public const METADATA_LOCALE = 'lostInTranslation::locale';
    public const METADATA_VALUE = 'lostInTranslation::value';
    public const METADATA_MISSING_IN_LOCALES = 'lostInTranslation::missingInLocales';
}
