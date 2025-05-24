<?php

/** @var \Illuminate\Contracts\Translation\Translator $translator */
$translator->get('contract basic');

/** @var \Illuminate\Translation\Translator $translator */
$translator->get('translator basic');
$translator->get('translator' . ' ' . 'basic');

/** @var string $dynamic */
$translator->get($dynamic);

/** @var "foo"|"bar" $multi */
$translator->get($multi);

/** @var "foo"|"bar"|\Exception $craycray */
$translator->get($craycray);
