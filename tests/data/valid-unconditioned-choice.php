<?php

/** Single-form locales use the same segment for every number. */
trans_choice('Only form', 2, [], 'ja');

/** The conventional singular/plural form remains valid. */
trans_choice('Singular|Plural', 2, [], 'en');

/** Some locales select among three unconditioned plural forms. */
trans_choice('One|Few|Many', 5, [], 'ru');

/** Explicit conditions and unconditioned forms may be combined. */
trans_choice('{0} None|One|Many', 5, [], 'ru');

/** @var int $number */
trans_choice('{0} None|One|Many', $number, [], 'ru');
