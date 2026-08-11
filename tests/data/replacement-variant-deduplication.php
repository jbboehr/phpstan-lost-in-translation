<?php

__(':foo', ['foo' => 'lower'], 'en');
__(':Foo', ['Foo' => 'title'], 'en');
__(':FOO', ['FOO' => 'upper'], 'en');

__(':élan', ['élan' => 'multibyte lower'], 'en');
__(':Élan', ['Élan' => 'multibyte title'], 'en');
__(':ÉLAN', ['ÉLAN' => 'multibyte upper'], 'en');

__(':foo :FOO', ['foo' => 'genuinely distinct'], 'en');

/** Laravel's title-case replacement variant is Unicode-aware. */
__(':Élan', ['élan' => 'multibyte title from lower-case key'], 'en');
