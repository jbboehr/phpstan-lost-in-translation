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

final class ShouldNotHappenException extends \RuntimeException
{
    private const URL = 'https://github.com/jbboehr/phpstan-lost-in-translation/issues';

    /**
     * @throws self
     */
    public static function rethrow(\Throwable $e): never
    {
        throw new self($e->getMessage(), $e);
    }

    public function __construct(
        string $message = 'Internal error',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('%s, please open an issue on GitHub %s', $message, self::URL),
            0,
            $previous,
        );
    }
}
