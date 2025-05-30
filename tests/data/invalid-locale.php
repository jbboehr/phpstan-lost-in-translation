<?php

/** This has no translation strings and also is not a correct identifier */
__('foobar', [], 'invalid_locale');

/** This is a correct identifier but is not the base locale and has no translation strings */
__('foobar', [], 'pt_BR');

/** This has both translations strings and is a locale identifier (no error) */
__('foobar', [], 'ja');
