<?php

__(':foo', ['foo' => 'lower'], 'en');
__(':Foo', ['Foo' => 'title'], 'en');
__(':FOO', ['FOO' => 'upper'], 'en');

__(':élan', ['élan' => 'multibyte lower'], 'en');
__(':Élan', ['Élan' => 'multibyte title'], 'en');
__(':ÉLAN', ['ÉLAN' => 'multibyte upper'], 'en');

__(':foo :FOO', ['foo' => 'genuinely distinct'], 'en');
