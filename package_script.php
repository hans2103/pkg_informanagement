<?php

/**
 * @package     Informanagement
 * @subpackage  pkg_informanagement
 *
 * @copyright   (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Installation script for the Informanagement package.
 *
 * @since  26.48.0
 */
class Pkg_InformanagementInstallerScript
{
	/**
	 * Minimum Joomla version required to install the package.
	 *
	 * @var    string
	 * @since  26.48.0
	 */
	protected $minimumJoomla = '5.2.0';

	/**
	 * Minimum PHP version required to install the package.
	 *
	 * @var    string
	 * @since  26.48.0
	 */
	protected $minimumPhp = '8.3.0';

	/**
	 * Checks the PHP and Joomla versions before an install or update.
	 *
	 * @param   string            $type    install, update, discover_install or uninstall
	 * @param   InstallerAdapter  $parent  The class calling this method
	 *
	 * @return  boolean  True to continue
	 *
	 * @since   26.48.0
	 */
	public function preflight(string $type, InstallerAdapter $parent): bool
	{
		if ($type === 'uninstall')
		{
			return true;
		}

		if (version_compare(PHP_VERSION, $this->minimumPhp, '<'))
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_INFORMANAGEMENT_MIN_PHP', $this->minimumPhp), 'error');

			return false;
		}

		if (version_compare(JVERSION, $this->minimumJoomla, '<'))
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_INFORMANAGEMENT_MIN_JOOMLA', $this->minimumJoomla), 'error');

			return false;
		}

		return true;
	}

	/**
	 * Enables the task and system plugins after a first installation; an update keeps the site's choice.
	 *
	 * @param   string            $type    install, update or discover_install
	 * @param   InstallerAdapter  $parent  The class calling this method
	 *
	 * @return  void
	 *
	 * @since   26.48.0
	 */
	public function postflight(string $type, InstallerAdapter $parent): void
	{
		if ($type !== 'install' && $type !== 'discover_install')
		{
			return;
		}

		$db      = Factory::getContainer()->get(DatabaseInterface::class);
		$extType = 'plugin';
		$element = 'informanagement';
		$query   = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = 1')
			->where($db->quoteName('type') . ' = :type')
			->where($db->quoteName('element') . ' = :element')
			->whereIn($db->quoteName('folder'), ['task', 'system'], ParameterType::STRING)
			->bind(':type', $extType, ParameterType::STRING)
			->bind(':element', $element, ParameterType::STRING);

		$db->setQuery($query)->execute();
	}
}
