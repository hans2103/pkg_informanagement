<?php

declare(strict_types=1);

/**
 * When a run in which Informanagement was unavailable is reported (KNOCKOUT and the failure mail).
 * Run: php tests/unit/FailureCounterTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Informanagement\Feed\FeedException;
use HKweb\Plugin\Task\Informanagement\Feed\FeedUnavailableException;
use HKweb\Plugin\Task\Informanagement\Import\FailureCounter;

check('first of three is not reported', FailureCounter::shouldReport(1, 3), false);
check('second of three is not reported', FailureCounter::shouldReport(2, 3), false);
check('third of three is reported', FailureCounter::shouldReport(3, 3), true);
check('later failures stay reported', FailureCounter::shouldReport(7, 3), true);
check('threshold 1 reports every time', FailureCounter::shouldReport(1, 1), true);
check('threshold 0 is treated as 1', FailureCounter::shouldReport(1, 0), true);

// An outage is still a FeedException, so code that catches FeedException keeps working.
check('unavailable is a FeedException', new FeedUnavailableException('down') instanceof FeedException, true);

finish('FailureCounter');
