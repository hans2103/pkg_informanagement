<?php

/**
 * @package     HKweb.Informanagement
 * @subpackage  plg_system_informanagement
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\System\Informanagement\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sends links with ?im_id=… (from the Informanagement newsletters) to the imported article.
 *
 * Matches the current feed id and the id of the original, so links to a revised article still work.
 *
 * @since  1.0.0
 */
final class Informanagement extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	/**
	 * Returns the events this plugin listens to.
	 *
	 * @return  array<string, string>
	 *
	 * @since   1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return ['onAfterRoute' => 'redirect'];
	}

	/**
	 * Redirects ?im_id=… on the site to the imported article (301), or to the home page (302) when it is not there.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function redirect(): void
	{
		$app = $this->getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		$imId = $app->getInput()->getInt('im_id', 0);

		if ($imId < 1)
		{
			return;
		}

		// One variable per placeholder: bind() takes references.
		$sourceId = $imId;
		$db       = $this->getDatabase();
		$query    = $db->getQuery(true)
			->select($db->quoteName(['c.id', 'c.alias', 'c.catid', 'c.language']))
			->from($db->quoteName('#__informanagement_articles', 'i'))
			->join('INNER', $db->quoteName('#__content', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('i.article_id'))
			->where([
				'(' . $db->quoteName('i.im_id') . ' = :id OR ' . $db->quoteName('i.source_id') . ' = :source)',
				$db->quoteName('c.state') . ' = 1',
				]
			)
			->bind(':id', $imId, ParameterType::INTEGER)
			->bind(':source', $sourceId, ParameterType::INTEGER);

		$article = $db->setQuery($query, 0, 1)->loadObject();

		if ($article === null)
		{
			// Not imported (yet): the article may arrive with the next import, so no permanent redirect.
			// @phpstan-ignore method.notFound (redirect() is on CMSApplication, not on CMSApplicationInterface.)
			$app->redirect(Uri::root(), 302);
		}

		// @phpstan-ignore argument.type (Joomla documents $id as integer, but takes the id:alias slug, as core does.)
		$route = RouteHelper::getArticleRoute($article->id . ':' . $article->alias, $article->catid, $article->language);

		// @phpstan-ignore method.notFound (redirect() is on CMSApplication, not on CMSApplicationInterface.)
		$app->redirect(Route::_($route, false), 301);
	}
}
