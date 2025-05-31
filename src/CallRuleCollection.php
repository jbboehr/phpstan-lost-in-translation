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

use IteratorAggregate;
use PHPStan\DependencyInjection\Container;
use PHPStan\DependencyInjection\ParameterNotFoundException;
use Traversable;

/**
 * @implements IteratorAggregate<int, CallRuleInterface>
 */
final class CallRuleCollection implements IteratorAggregate, \Countable
{
    private const FLAG_MAP = [
        'disallowDynamicTranslationStrings' => DynamicTranslationStringRule::class,
        'invalidChoices' => InvalidChoiceRule::class,
        'invalidLocales' => InvalidLocaleRule::class,
        'invalidReplacements' => InvalidReplacementRule::class,
        'missingTranslationStringsInBaseLocale' => MissingTranslationStringInBaseLocaleRule::class,
        'missingTranslationStrings' => MissingTranslationStringRule::class,
    ];

    /**
     * @var list<CallRuleInterface>
     */
    private array $rules = [];

    /**
     * @param list<CallRuleInterface> $rules
     * @return self
     */
    public static function createFromArray(array $rules): self
    {
        $self = new self(null);
        $self->rules = $rules;
        return $self;
    }

    public function __construct(
        ?Container $container,
    ) {
        if ($container === null) {
            return;
        }

        try {
            $flags = $container->getParameter('lostInTranslation');
        } catch (ParameterNotFoundException) {
            return;
        }

        if (!is_array($flags)) {
            return;
        }

        $rules = [];

        foreach ($flags as $key => $_) {
            if (false !== $_ && isset(self::FLAG_MAP[$key])) {
                $rules[] = $container->getByType(self::FLAG_MAP[$key]);
            }
        }

        $this->rules = $rules;
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->rules);
    }

    public function count(): int
    {
        return count($this->rules);
    }
}
