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

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Counts the runs in a row in which Informanagement was unavailable, per scheduled task.
 *
 * @since  1.0.2
 */
final class FailureCounter
{
	/**
	 * Constructor.
	 *
	 * @param   DatabaseInterface  $db  The database.
	 *
	 * @since   1.0.2
	 */
	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	/**
	 * Records one more failed run.
	 *
	 * @param   integer  $taskId  The scheduled task.
	 *
	 * @return  integer  The number of failed runs in a row, including this one.
	 *
	 * @since   1.0.2
	 */
	public function increment(int $taskId): int
	{
		$table = $this->db->quoteName('#__informanagement_failures');
		$since = gmdate('Y-m-d H:i:s');

		// MySQL/MariaDB upsert: the query builder has none, the values are bound.
		$query = $this->db->createQuery()
			->setQuery(
				'INSERT INTO ' . $table . ' (' . $this->db->quoteName('task_id') . ', ' . $this->db->quoteName('failures') . ', '
				. $this->db->quoteName('since') . ') VALUES (:taskId, 1, :since) ON DUPLICATE KEY UPDATE '
				. $this->db->quoteName('failures') . ' = ' . $this->db->quoteName('failures') . ' + 1'
			)
			->bind(':taskId', $taskId, ParameterType::INTEGER)
			->bind(':since', $since);

		$this->db->setQuery($query)->execute();

		return $this->count($taskId);
	}

	/**
	 * Clears the count after a successful run.
	 *
	 * @param   integer  $taskId  The scheduled task.
	 *
	 * @return  integer  The number of failed runs in a row before this success (0: none).
	 *
	 * @since   1.0.2
	 */
	public function reset(int $taskId): int
	{
		$previous = $this->count($taskId);

		if ($previous > 0)
		{
			$query = $this->db->createQuery()
				->delete($this->db->quoteName('#__informanagement_failures'))
				->where($this->db->quoteName('task_id') . ' = :taskId')
				->bind(':taskId', $taskId, ParameterType::INTEGER);

			$this->db->setQuery($query)->execute();
		}

		return $previous;
	}

	/**
	 * Whether a run that failed should be reported (status KNOCKOUT, and so the failure mail).
	 *
	 * @param   integer  $failures   The failed runs in a row, including this one.
	 * @param   integer  $threshold  Report from this many failures in a row on (1: report every failure).
	 *
	 * @return  boolean
	 *
	 * @since   1.0.2
	 */
	public static function shouldReport(int $failures, int $threshold): bool
	{
		return $failures >= max(1, $threshold);
	}

	/**
	 * The current count.
	 *
	 * @param   integer  $taskId  The scheduled task.
	 *
	 * @return  integer
	 *
	 * @since   1.0.2
	 */
	private function count(int $taskId): int
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName('failures'))
			->from($this->db->quoteName('#__informanagement_failures'))
			->where($this->db->quoteName('task_id') . ' = :taskId')
			->bind(':taskId', $taskId, ParameterType::INTEGER);

		return (int) $this->db->setQuery($query)->loadResult();
	}
}
