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

use PHPStan\Rules\IdentifierRuleError;

final class LoadResult
{
    /**
     * @param array<non-empty-string, non-empty-string> $translations
     * @param array<non-empty-string, int> $locations
     * @param list<IdentifierRuleError> $errors
     * @param list<non-empty-string> $arrayKeys
     */
    public function __construct(
        public readonly array $translations,
        public readonly array $locations,
        public readonly array $errors,
        public readonly array $arrayKeys = [],
    ) {
    }
}
