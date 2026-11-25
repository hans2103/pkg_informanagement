<?php

/**
 * @package     HKweb.Informanagement
 * @subpackage  plg_task_informanagement
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use HKweb\Plugin\Task\Informanagement\Extension\Informanagement;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Http\HttpFactory;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the task plugin in the DI container, created lazily.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			$container->lazy(
				Informanagement::class,
				static function (Container $container): PluginInterface {
					$plugin = new Informanagement(
						(array) PluginHelper::getPlugin('task', 'informanagement'),
						new HttpFactory
					);
					$plugin->setApplication(Factory::getApplication());
					$plugin->setDatabase($container->get(DatabaseInterface::class));

					return $plugin;
				}
			)
		);
	}
};
