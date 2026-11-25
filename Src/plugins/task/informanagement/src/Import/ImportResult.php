<?php

/**
 * @package     HKweb.Informanagement
 * @subpackage  plg_task_informanagement
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\Task\Informanagement\Import;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Counts of one import run.
 *
 * @since  1.0.0
 */
final class ImportResult
{
	/**
	 * New articles.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $created = 0;

	/**
	 * Articles updated from the feed.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $updated = 0;

	/**
	 * Articles left alone: unchanged in the feed, or updates switched off.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $unchanged = 0;

	/**
	 * Articles kept as they are: deleted, trashed or edited by the editors.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $kept = 0;

	/**
	 * Items that could not be imported.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $failed = 0;
}
