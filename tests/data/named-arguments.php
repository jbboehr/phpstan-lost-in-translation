<?php

__('exists in all locales', locale: 'pt_BR');
trans(locale: 'pt_BR', key: 'exists in all locales');
trans_choice('exists in all locales', number: 1, locale: 'pt_BR');
trans_choice(locale: 'pt_BR', number: 1, key: 'exists in all locales');

/** @var \Illuminate\Contracts\Translation\Translator $translator */
$translator->get(locale: 'pt_BR', key: 'exists in all locales');
$translator->get('exists in all locales', locale: 'pt_BR');
$translator->choice(number: 1, key: 'exists in all locales', locale: 'pt_BR');

\Illuminate\Support\Facades\Lang::get(key: 'exists in all locales', locale: 'pt_BR');
\Illuminate\Support\Facades\Lang::get('exists in all locales', locale: 'pt_BR');
\Illuminate\Support\Facades\Lang::choice(locale: 'pt_BR', number: 1, key: 'exists in all locales');
