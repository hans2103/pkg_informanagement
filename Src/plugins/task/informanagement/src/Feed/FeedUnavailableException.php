<?php

/**
 * @package     HKweb.Informanagement
 * @subpackage  plg_task_informanagement
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\Task\Informanagement\Feed;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Informanagement is temporarily unavailable: no answer, an HTTP error or no valid feed (a maintenance page).
 * The next run may well succeed, so the task only reports it after several failures in a row.
 *
 * @since  1.0.2
 */
final class FeedUnavailableException extends FeedException
{
}
