<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Base test case
|--------------------------------------------------------------------------
|
| Every test routes through Tests\TestCase, whose setUp() refuses to run
| against a database that does not end in _test and blocks stray HTTP
| requests. Nothing in the suite may bypass it.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Database traits
|--------------------------------------------------------------------------
|
| RefreshDatabase is applied per file, not here. 103 of the 125 test classes
| use it, but ~22 deliberately do not, and tests/Feature/Web/SearchTest.php
| needs DatabaseTruncation instead — the FULLTEXT index only sees committed
| rows. A blanket ->use(RefreshDatabase::class) would silently change all of
| those.
|
*/
