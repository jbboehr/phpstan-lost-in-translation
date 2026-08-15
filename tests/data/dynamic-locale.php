<?php

$locale = (string) getenv('LOCALE');
__('missing with dynamic locale', [], $locale);

$mixedLocale = rand(0, 1) ? 0 : 'en';
__('missing with mixed falsey scalar locale', [], $mixedLocale);

$mixedFalseLocale = rand(0, 1) ? false : 'en';
__('missing with mixed false locale', [], $mixedFalseLocale);

$mixedNullLocale = rand(0, 1) ? null : 'en';
__('missing with mixed null locale', [], $mixedNullLocale);

/** @param array-key $benevolentLocale */
function checkBenevolentLocale($benevolentLocale): void
{
    if (0 !== $benevolentLocale && 'en' !== $benevolentLocale) {
        return;
    }

    __('only in en', [], $benevolentLocale);
}
