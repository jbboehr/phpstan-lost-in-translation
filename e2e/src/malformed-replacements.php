<?php

/* has a replacement that doesn't exist in the translation key */
__('exists in all locales', ['foo' => 'bar', 'bar' => 'bat'], 'en');

/* has multiple replacement variants */
__(':foo :FOO', ['foo' => 'bar'], 'en');
