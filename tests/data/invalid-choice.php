<?php

/** this is normal */
trans_choice('{0} There are none|{1} There is one|[2,*] There are :count', 2, [], 'en');

/** does not contain a case for number */
trans_choice('{0} There are none|{1} There is one|[2] There are :count', 3, [], 'en');

/** does not contain a case for number */
trans_choice('{4,*} There are many|{3} There are three', 2, [], 'en');

/** try a generic integer */
/** @var int $n1 */
/** this will error as it does not cover [-inf, 2] */
trans_choice('{4,*} There are many|{3} There are three', $n1, [], 'en');

/** this one should not error as it covers all possible integers */
trans_choice('{4,*} There are four or more|{*,3} There are three or less', $n1, [], 'en');

/** try an integer range */
/** @var int<2, 3> $n2 */

/** will not error as range is covereged */
trans_choice('{2} There are two|{3} There are three', $n2, [], 'en');

/** @var int<2, 4> $n3 */

/** will error as range is not covered */
trans_choice('{2} There are two|{3} There are three', $n3, [], 'en');
