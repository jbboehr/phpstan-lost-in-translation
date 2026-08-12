<?php

/** A one-form locale remains complete. */
trans_choice('Only form', 2, [], 'ja');

/** English can select singular and plural positions. */
trans_choice('Only form', 2, [], 'en');

/** Both English positions are present. */
trans_choice('One|Many', 2, [], 'en');

/** Russian can select three positions. */
trans_choice('One|Many', 5, [], 'ru');

/** All three Russian positions are present. */
trans_choice('One|Few|Many', 5, [], 'ru');

/** Arabic can select six positions. */
trans_choice('Zero|One|Two|Other', 11, [], 'ar');

/** All six Arabic positions are present. */
trans_choice('Zero|One|Two|Few|Many|Other', 11, [], 'ar');

/** This reproduces the single-form Icelandic integration finding. */
trans_choice('A revision', 2, [], 'is');

/** Application aliases use their configured plural policy without changing the diagnostic locale. */
trans_choice('Application form', 2, [], 'APPLICATION-PLURAL');

/** Slovenian can select four positions. */
trans_choice('One|Two|Other', 3, [], 'sl');

/** All four Slovenian positions are present. */
trans_choice('One|Two|Few|Other', 3, [], 'sl');
