<?php

$replace = rand(0, 1)
    ? ['same' => 'first']
    : ['same' => 'second', 'other' => 'third'];

__('exists in all locales', $replace, 'en');
