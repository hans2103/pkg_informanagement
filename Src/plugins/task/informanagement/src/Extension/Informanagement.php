<?php

/**
 * @package     HKweb.Informanagement
 * @subpackage  plg_task_informanagement
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\Task\Informanagement\Extension;

use HKweb\Plugin\Task\Informanagement\Feed\FeedException;
use HKweb\Plugin\Task\Informanagement\Feed\FeedUnavailableException;
use HKweb\Plugin\Task\Informanagement\Import\FailureCounter;
use HKweb\Plugin\Task\Informanagement\Import\Importer;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Scheduler routine that imports the Informanagement news feed as Joomla articles.
 *
 * @since  1.0.0
 */
final class Informanagement extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;
	use TaskPluginTrait;

	/**
	 * The routines this plugin offers to the scheduler.
	 *
	 * @var    array<string, array{langConstPrefix: string, form: string, method: string}>
	 * @since  1.0.0
	 */
	protected const TASKS_MAP = [
		'informanagement.import' => [
			'langConstPrefix' => 'PLG_TASK_INFORMANAGEMENT_TASK_IMPORT',
			'form'            => 'import',
			'method'          => 'import',
		],
	];

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Constructor.
	 *
	 * @param   array<string, mixed>  $config       The plugin configuration.
	 * @param   HttpFactory           $httpFactory  Creates the HTTP client for the feed and the images.
	 *
	 * @since   1.0.0
	 */
	public function __construct(array $config, private readonly HttpFactory $httpFactory)
	{
		parent::__construct($config);
	}

	/**
	 * Returns the events this plugin listens to.
	 *
	 * @return  array<string, string>
	 *
	 * @since   1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * The routine informanagement.import: imports the feed with the settings of the task.
	 *
	 * When Informanagement is unavailable, the run only counts as failed (KNOCKOUT, and so the failure
	 * mail) from the setting notify_after failed runs in a row on; before that it logs a warning.
	 *
	 * @param   ExecuteTaskEvent  $event  The task event.
	 *
	 * @return  integer  A TaskStatus constant: OK, or KNOCKOUT when the feed failed or nothing could be imported.
	 *
	 * @since   1.0.0
	 */
	protected function import(ExecuteTaskEvent $event): int
	{
		$params   = new Registry($event->getArgument('params'));
		$language = $this->getApplication()->getLanguage();
		$counter  = new FailureCounter($this->getDatabase());
		$taskId   = $event->getTaskId();
		$importer = new Importer(
			$this->getDatabase(),
			$this->httpFactory,
			$this->getApplication(),
			$this->logTask(...)
		);

		try
		{
			$result = $importer->run($params);
		}
		catch (FeedUnavailableException $e)
		{
			$failures  = $counter->increment($taskId);
			$threshold = (int) $params->get('notify_after', 3);

			if (!FailureCounter::shouldReport($failures, $threshold))
			{
				$this->logTask(\sprintf($language->_('PLG_TASK_INFORMANAGEMENT_LOG_RETRY'), $e->getMessage(), $failures, $threshold), 'warning');

				return TaskStatus::OK;
			}

			$this->logTask(\sprintf($language->_('PLG_TASK_INFORMANAGEMENT_LOG_FAILED'), $e->getMessage(), $failures), 'error');

			return TaskStatus::KNOCKOUT;
		}
		catch (FeedException $e)
		{
			$this->logTask($e->getMessage(), 'error');

			return TaskStatus::KNOCKOUT;
		}

		$previous = $counter->reset($taskId);

		if ($previous > 0)
		{
			$this->logTask(\sprintf($language->_('PLG_TASK_INFORMANAGEMENT_LOG_RECOVERED'), $previous));
		}

		$message = \sprintf(
			$language->_('PLG_TASK_INFORMANAGEMENT_LOG_RESULT'),
			$result->created,
			$result->updated,
			$result->unchanged,
			$result->kept,
			$result->failed
		);
		$this->logTask($message);

		return $result->failed > 0 && $result->created + $result->updated + $result->unchanged + $result->kept === 0
			? TaskStatus::KNOCKOUT
			: TaskStatus::OK;
	}
}
