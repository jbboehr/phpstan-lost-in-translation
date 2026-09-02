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

namespace jbboehr\PHPStanLostInTranslation;

/**
 * @api
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
    public const DYNAMIC_TRANSLATION_STRING = 'lostInTranslation.dynamicTranslationString';
    public const INVALID_CHARACTER_ENCODING = 'lostInTranslation.invalidCharacterEncoding';
    public const INVALID_CHOICE_MALFORMED = 'lostInTranslation.invalidChoice.malformed';
    public const INVALID_CHOICE_MISSING_CASE = 'lostInTranslation.invalidChoice.missingCase';
    public const INVALID_CHOICE_MISSING_PLURAL_FORM = 'lostInTranslation.invalidChoice.missingPluralForm';
    public const INVALID_CHOICE_NON_NUMERIC = 'lostInTranslation.invalidChoice.nonNumeric';
    public const INVALID_LOCALE_NO_TRANSLATIONS = 'lostInTranslation.invalidLocale.noTranslations';
    public const INVALID_LOCALE_UNKNOWN = 'lostInTranslation.invalidLocale.unknown';
    public const INVALID_REPLACEMENT_MULTIPLE_VARIANTS = 'lostInTranslation.invalidReplacement.multipleVariants';
    public const INVALID_REPLACEMENT_UNUSED = 'lostInTranslation.invalidReplacement.unused';
    public const MISSING_BASE_LOCALE_TRANSLATION_STRING = 'lostInTranslation.missingBaseLocaleTranslationString';
    public const MISSING_TRANSLATION_STRING = 'lostInTranslation.missingTranslationString';
    public const POSSIBLY_UNUSED_TRANSLATION_STRING = 'lostInTranslation.possiblyUnusedTranslationString';
    public const TRANSLATION_LOADER_ERROR = 'lostInTranslation.translationLoaderError';
    public const TRANSLATION_LOADER_ERROR_CONFLICTING_KEY = 'lostInTranslation.translationLoaderError.conflictingKey';
    public const TRANSLATION_LOADER_ERROR_CONFLICTING_LOCALE = 'lostInTranslation.translationLoaderError.conflictingLocale';

    public const METADATA_KEY = 'lostInTranslation::key';
    public const METADATA_LOCALE = 'lostInTranslation::locale';
    public const METADATA_VALUE = 'lostInTranslation::value';
    public const METADATA_MISSING_IN_LOCALES = 'lostInTranslation::missingInLocales';
}
