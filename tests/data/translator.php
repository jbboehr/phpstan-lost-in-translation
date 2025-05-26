<?php

/** @var \Illuminate\Contracts\Translation\Translator $translator */
$translator->get('contract basic');

/** @var \Illuminate\Translation\Translator $translator */
$translator->get('translator basic');
$translator->get('translator' . ' ' . 'basic');




/** @var "foo"|"bar" $multi */
$translator->get($multi);
