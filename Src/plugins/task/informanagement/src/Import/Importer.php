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

use HKweb\Plugin\Task\Informanagement\Feed\ArticleMapper;
use HKweb\Plugin\Task\Informanagement\Feed\FeedException;
use HKweb\Plugin\Task\Informanagement\Feed\FeedItem;
use HKweb\Plugin\Task\Informanagement\Feed\FeedParser;
use HKweb\Plugin\Task\Informanagement\Feed\FeedUnavailableException;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Category;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Version;
use Joomla\CMS\Workflow\Workflow;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Imports the Informanagement feed into com_content.
 *
 * One request for the list; the detail feed (for the source line) is only fetched for articles
 * that are new or changed. The table #__informanagement_articles links feed ids to articles.
 *
 * @since  1.0.0
 */
final class Importer
{
	/**
	 * Feed URL; %s is the location (nl, uk, de).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const FEED       = 'https://%s.informanagement.com/rss/customfeed.aspx';
	private const IMAGE_DIR  = 'images/informanagement';
	private const MAX_IMAGE  = 8 * 1024 * 1024;
	private const IMAGE_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

	/**
	 * HTML that may stay in an article; style, class, border and the like go.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	private const TAGS  = ['p', 'br', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'sub', 'sup',
		'a', 'blockquote', 'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'hr'];

	/**
	 * Attributes that may stay on the allowed tags.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	private const ATTRS = ['href', 'title', 'scope', 'colspan', 'rowspan'];

	/**
	 * Category ids by alias, for the subcategories found or created during this run.
	 *
	 * @var    array<string, int>
	 * @since  1.0.0
	 */
	private array $categories = [];

	/**
	 * Receives a message and a priority.
	 *
	 * @var    \Closure(string, string): void
	 * @since  1.0.0
	 */
	private \Closure $log;

	/**
	 * Constructor.
	 *
	 * @param   DatabaseInterface               $db           The database.
	 * @param   HttpFactory                     $httpFactory  Creates the HTTP client for the feed and the images.
	 * @param   CMSApplicationInterface         $app          The application, for the dispatcher and the configuration.
	 * @param   callable(string, string): void  $log          Receives a message and a priority (info, warning, error).
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		private readonly DatabaseInterface $db,
		private readonly HttpFactory $httpFactory,
		private readonly CMSApplicationInterface $app,
		callable $log,
	) {
		$this->log = $log(...);
	}

	/**
	 * Runs one import: fetches the list feed and imports each new or changed article.
	 *
	 * @param   Registry  $params  The task settings.
	 *
	 * @return  ImportResult  The counts of this run.
	 *
	 * @throws  FeedUnavailableException  When Informanagement is unavailable; nothing is changed then.
	 * @throws  FeedException             When the task settings are incomplete.
	 *
	 * @since   1.0.0
	 */
	public function run(Registry $params): ImportResult
	{
		$template = (string) $params->get('template', '');
		$location = (string) $params->get('location', 'nl');
		$parentId = (int) $params->get('catid', 0);

		if (!preg_match('/^[A-Za-z0-9_-]+$/', $template) || !\in_array($location, ['nl', 'uk', 'de'], true) || $parentId < 1)
		{
			throw new FeedException('Sjabloon, locatie of categorie ontbreekt in de taakinstellingen.');
		}

		$number = max(1, min(500, (int) $params->get('number', 25)));
		$parser = new FeedParser;
		$items  = $parser->parse($this->fetch($this->feedUrl($location, ['command' => 'rss', 'nr' => $number, 'length' => ''], $template)));
		$mapper = new ArticleMapper($location, (bool) $params->get('source', 1), $this->sanitizer());
		$result = new ImportResult;

		PluginHelper::importPlugin('content');

		// Oldest first, so a revision is handled after the article it replaces.
		foreach (array_reverse($items) as $item)
		{
			try
			{
				$this->importItem($item, $params, $parser, $mapper, $location, $template, $result);
			}
			catch (\Throwable $e)
			{
				$result->failed++;
				($this->log)(\sprintf('Artikel %d (%s): %s', $item->id, $item->title, $e->getMessage()), 'error');
			}
		}

		return $result;
	}

	/**
	 * Creates, updates or skips the article for one feed item.
	 *
	 * @param   FeedItem       $item      The item from the list feed.
	 * @param   Registry       $params    The task settings.
	 * @param   FeedParser     $parser    Parser for the detail feed.
	 * @param   ArticleMapper  $mapper    Turns the detail item into article fields.
	 * @param   string         $location  Informanagement location (nl, uk, de).
	 * @param   string         $template  Informanagement template (sjabloon).
	 * @param   ImportResult   $result    The counts, updated in place.
	 *
	 * @return  void
	 *
	 * @throws  FeedException      When the detail feed has no matching article.
	 * @throws  \RuntimeException  When the article or its category can't be saved.
	 *
	 * @since   1.0.0
	 */
	private function importItem(
		FeedItem $item,
		Registry $params,
		FeedParser $parser,
		ArticleMapper $mapper,
		string $location,
		string $template,
		ImportResult $result,
	): void {
		$link = $this->findLink($item);

		if ($link !== null && $link->hash === $item->hash() && (int) $link->im_id === $item->id)
		{
			$result->unchanged++;

			return;
		}

		$article = new Content($this->db);

		if ($link !== null)
		{
			// Deleted or trashed by the editors: leave it that way.
			if (!$article->load((int) $link->article_id) || (int) $article->state === -2)
			{
				$result->kept++;

				return;
			}

			if (!$params->get('update', 1))
			{
				$result->unchanged++;

				return;
			}

			if ($params->get('keep_edits', 1) && $link->modified !== null && $article->modified !== $link->modified)
			{
				($this->log)(\sprintf('Artikel %d is door de redactie aangepast en blijft zoals het is.', (int) $article->id), 'info');
				$this->saveLink($item, (int) $article->id, (string) $link->image, (string) $link->modified, (int) $link->im_id);
				$result->kept++;

				return;
			}
		}

		// The detail feed adds the source fields (agency, character, number).
		$detail = $parser->parse($this->fetch($this->feedUrl($location, ['command' => 'viewitem', 'id' => $item->id], $template)))[0] ?? null;

		if ($detail === null || $detail->id !== $item->id)
		{
			throw new FeedException('Detailfeed gaf geen artikel terug.');
		}

		$data  = $mapper->map($detail);
		$isNew = $link === null;
		$image = $isNew ? '' : (string) $link->image;

		$article->title     = $data['title'];
		$article->introtext = $data['introtext'];
		$article->fulltext  = $data['fulltext'];
		$article->metadesc  = $data['metadesc'];

		if ($isNew)
		{
			$article->catid      = $this->categoryFor($detail->category, $params);
			$article->alias      = $this->uniqueAlias($data['title'], (int) $article->catid);
			$article->state      = (int) $params->get('state', 1);
			$article->created    = $data['created'];
			$article->publish_up = $data['created'];
			$article->created_by = (int) $params->get('created_by', 0) ?: $this->fallbackAuthor();
			$article->access     = 1;
			$article->language   = '*';
			$article->featured   = 0;
			$article->version    = 1;
			$article->attribs    = '{}';
			$article->urls       = '{}';
			$article->metakey    = '';
			$article->metadata   = '{"robots":"","author":"","rights":""}';
			$article->images     = '{}';
		}

		// Alias, category and publication date stay as they are on an update: the URL may not change.
		$article->note = 'informanagement:' . $detail->sourceId;

		if ($params->get('images', 1))
		{
			$image = $this->updateImage($article, $detail, $image, (int) $params->get('image_width', 1200), (bool) $params->get('image_jpeg', 1));
		}

		$this->store($article, $isNew);
		$this->saveLink($detail, (int) $article->id, $image, (string) $article->modified, $isNew ? null : (int) $link->im_id);

		$isNew ? $result->created++ : $result->updated++;
	}

	/**
	 * The link for this item: by its own id, or for a revision by the id of the original.
	 *
	 * @param   FeedItem  $item  The feed item.
	 *
	 * @return  ?object  The row of #__informanagement_articles, or null when the item is new.
	 *
	 * @since   1.0.0
	 */
	private function findLink(FeedItem $item): ?object
	{
		// The bind() method takes references, which readonly properties can't give.
		$id       = $item->id;
		$sourceId = $item->sourceId;
		$query    = $this->db->getQuery(true)
			->select('*')
			->from($this->db->quoteName('#__informanagement_articles'))
			->where($this->db->quoteName('im_id') . ' IN (:id, :source)')
			->bind(':id', $id, ParameterType::INTEGER)
			->bind(':source', $sourceId, ParameterType::INTEGER)
			->order($this->db->quoteName('updated') . ' DESC');

		return $this->db->setQuery($query, 0, 1)->loadObject() ?: null;
	}

	/**
	 * Inserts or updates the row in #__informanagement_articles that links the feed id to the article.
	 *
	 * @param   FeedItem  $item        The feed item.
	 * @param   int       $articleId   Id of the article.
	 * @param   string    $image       Key of the downloaded image.
	 * @param   string    $modified    Modified date of the article as stored; empty for none.
	 * @param   ?int      $previousId  The im_id the row has now; null to insert a new row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function saveLink(FeedItem $item, int $articleId, string $image, string $modified, ?int $previousId = null): void
	{
		$now = Factory::getDate()->toSql();

		$row = (object) [
			'im_id'      => $item->id,
			'source_id'  => $item->sourceId,
			'article_id' => $articleId,
			'hash'       => $item->hash(),
			'image'      => $image,
			'modified'   => $modified ?: null,
			'updated'    => $now,
		];

		if ($previousId === null)
		{
			$row->imported = $now;
			$this->db->insertObject('#__informanagement_articles', $row);

			return;
		}

		// The im_id column is the key and changes for a revision, so update by the previous id.
		$query = $this->db->getQuery(true)
			->update($this->db->quoteName('#__informanagement_articles'))
			->set(
				[
					$this->db->quoteName('im_id') . ' = :im',
					$this->db->quoteName('source_id') . ' = :source',
					$this->db->quoteName('hash') . ' = :hash',
					$this->db->quoteName('image') . ' = :image',
					$this->db->quoteName('modified') . ' = :modified',
					$this->db->quoteName('updated') . ' = :updated',
				]
			)
			->where($this->db->quoteName('im_id') . ' = :previous')
			->bind(':im', $row->im_id, ParameterType::INTEGER)
			->bind(':source', $row->source_id, ParameterType::INTEGER)
			->bind(':hash', $row->hash, ParameterType::STRING)
			->bind(':image', $row->image, ParameterType::STRING)
			->bind(':modified', $row->modified, $row->modified === null ? ParameterType::NULL : ParameterType::STRING)
			->bind(':updated', $row->updated, ParameterType::STRING)
			->bind(':previous', $previousId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Checks and stores the article with the content events, as the article model would.
	 *
	 * @param   Content  $article  The article.
	 * @param   bool     $isNew    Whether the article is new.
	 *
	 * @return  void
	 *
	 * @throws  \RuntimeException  When the article can't be saved or a content plugin stops it.
	 *
	 * @since   1.0.0
	 */
	private function store(Content $article, bool $isNew): void
	{
		$dispatcher = $this->app->getDispatcher();
		$context    = 'com_content.article';
		$data       = $article->getProperties();

		if (!$article->check())
		{
			throw new \RuntimeException($article->getError());
		}

		$arguments = [
			'context' => $context,
			'subject' => $article,
			'isNew'   => $isNew,
			'data'    => $data,
		];

		$before = $dispatcher->dispatch('onContentBeforeSave', new BeforeSaveEvent('onContentBeforeSave', $arguments));

		if (\in_array(false, $before->getArgument('result', []), true))
		{
			throw new \RuntimeException('Opslaan tegengehouden door een contentplugin.');
		}

		if (!$article->store())
		{
			throw new \RuntimeException($article->getError());
		}

		// Without a workflow association the article is missing from Content > Articles.
		if ($isNew)
		{
			// @phpstan-ignore argument.type (Workflow asks for DatabaseDriver; the plugin gets the driver as DatabaseInterface.)
			$workflow = new Workflow('com_content.article', $this->app, $this->db);
			$workflow->createAssociation((int) $article->id, (int) $workflow->getDefaultStageByCategory((int) $article->catid));
		}

		$dispatcher->dispatch('onContentAfterSave', new AfterSaveEvent('onContentAfterSave', $arguments));
	}

	/**
	 * The parent category itself, or a subcategory per Informanagement section (created when needed).
	 *
	 * @param   string    $name    The Informanagement section.
	 * @param   Registry  $params  The task settings.
	 *
	 * @return  integer  The category id.
	 *
	 * @throws  \RuntimeException  When the subcategory can't be created.
	 *
	 * @since   1.0.0
	 */
	private function categoryFor(string $name, Registry $params): int
	{
		$parentId = (int) $params->get('catid');

		if (!$params->get('subcategories', 1) || $name === '')
		{
			return $parentId;
		}

		$alias = OutputFilter::stringURLSafe($name);

		if (isset($this->categories[$alias]))
		{
			return $this->categories[$alias];
		}

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName('id'))
			->from($this->db->quoteName('#__categories'))
			->where(
				[
					$this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'),
					$this->db->quoteName('parent_id') . ' = :parent',
					$this->db->quoteName('alias') . ' = :alias',
				]
			)
			->bind(':parent', $parentId, ParameterType::INTEGER)
			->bind(':alias', $alias, ParameterType::STRING);

		$id = (int) $this->db->setQuery($query)->loadResult();

		if ($id === 0)
		{
			$category = new Category($this->db);
			$category->setLocation($parentId, 'last-child');
			$category->bind(
				[
					'title'       => $name,
					'alias'       => $alias,
					'extension'   => 'com_content',
					'published'   => 1,
					'access'      => 1,
					'language'    => '*',
					'params'      => '{"category_layout":"","image":"","image_alt":"","image_alt_empty":"","workflow_id":"use_inherit"}',
					'metadata'    => '{"author":"","robots":""}',
					'description' => '',
				]
			);

			if (!$category->check() || !$category->store())
			{
				throw new \RuntimeException($category->getError());
			}

			$category->rebuildPath((int) $category->id);
			$id = (int) $category->id;
			($this->log)(\sprintf('Categorie "%s" aangemaakt.', $name), 'info');
		}

		return $this->categories[$alias] = $id;
	}

	/**
	 * An alias for the title that is not yet used in the category.
	 *
	 * @param   string  $title  The article title.
	 * @param   int     $catid  The category id.
	 *
	 * @return  string  The alias.
	 *
	 * @since   1.0.0
	 */
	private function uniqueAlias(string $title, int $catid): string
	{
		$unicode = (int) $this->app->get('unicodeslugs', 0) === 1;
		$alias   = $unicode ? OutputFilter::stringUrlUnicodeSlug($title) : OutputFilter::stringURLSafe($title);
		$alias   = $alias !== '' ? $alias : Factory::getDate()->format('Y-m-d-H-i-s');
		$table   = new Content($this->db);

		while ($table->load(['alias' => $alias, 'catid' => $catid]))
		{
			$alias = StringHelper::increment($alias, 'dash');
		}

		return $alias;
	}

	/**
	 * Downloads the image once into images/informanagement/YYYY/MM and sets it as intro and full image.
	 *
	 * @param   Content   $article  The article; its images are updated.
	 * @param   FeedItem  $item     The feed item.
	 * @param   string    $current  Key of the image the article has now.
	 * @param   int       $width    Requested width in pixels.
	 * @param   bool      $jpeg     Whether to request the image as JPEG.
	 *
	 * @return  string  The image URL (without query) the article now has.
	 *
	 * @since   1.0.0
	 */
	private function updateImage(Content $article, FeedItem $item, string $current, int $width, bool $jpeg): string
	{
		$url = $this->imageUrl($item->image, $width, $jpeg);

		if ($url === null)
		{
			return $current;
		}

		// The key is the URL with its options: another width or format means a new download.
		$key = $url;

		if ($key === $current || strtok($url, '?') === $current)
		{
			return $current;
		}

		$images = json_decode((string) $article->images, true) ?: [];
		$old    = (string) ($images['image_fulltext'] ?? '');

		$response = $this->http()->get($url, [], 30);
		$body     = (string) $response->getBody();
		$mime     = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
		$size     = $body !== '' && \strlen($body) <= self::MAX_IMAGE ? getimagesizefromstring($body) : false;

		if ($response->getStatusCode() !== 200 || !isset(self::IMAGE_MIME[$mime]) || $size === false || $size['mime'] !== $mime)
		{
			($this->log)(\sprintf('Afbeelding van artikel %d overgeslagen: geen geldige afbeelding.', $item->id), 'warning');

			return $current;
		}

		$published = $item->published->setTimezone(new \DateTimeZone('UTC'));
		$path      = (string) parse_url($url, PHP_URL_PATH);
		$name      = preg_replace('/[^a-z0-9-]/', '', strtolower(pathinfo($path, PATHINFO_FILENAME))) ?: 'im-' . $item->id;
		$relative  = self::IMAGE_DIR . '/' . $published->format('Y/m') . '/' . $name . '.' . self::IMAGE_MIME[$mime];
		$absolute  = JPATH_ROOT . '/' . $relative;

		Folder::create(\dirname($absolute));
		File::write($absolute, $body);

		$value = $relative . '#joomlaImage://local-' . $relative . '?width=' . $size[0] . '&height=' . $size[1];

		// No alt text in the feed; the image illustrates the title, so it is marked decorative.
		$images = array_merge(
			$images,
			[
				'image_intro'              => $value,
				'image_intro_alt'          => '',
				'image_intro_alt_empty'    => '1',
				'float_intro'              => '',
				'image_intro_caption'      => '',
				'image_fulltext'           => $value,
				'image_fulltext_alt'       => '',
				'image_fulltext_alt_empty' => '1',
				'float_fulltext'           => '',
				'image_fulltext_caption'   => '',
			]
		);
		$article->images = (string) json_encode($images, JSON_UNESCAPED_SLASHES);

		// Remove the previous download, only if it was ours and is not the same file.
		$oldPath = strtok($old, '#');
		$oldFile = \is_string($oldPath) && $oldPath !== $relative ? $this->ownImage($oldPath) : null;

		if ($oldFile !== null && $oldFile !== realpath($absolute))
		{
			File::delete($oldFile);
		}

		return $key;
	}

	/**
	 * The absolute path of an image this plugin downloaded, so it may be deleted.
	 *
	 * The path comes from the article's images field, which editors can change: a path like
	 * images/informanagement/../../configuration.php must never resolve to a file outside the folder.
	 *
	 * @param   string  $relative  Path relative to the site root, as stored in the article.
	 *
	 * @return  ?string  The resolved path, or null when it is no file inside images/informanagement.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function ownImage(string $relative): ?string
	{
		if (!str_starts_with($relative, self::IMAGE_DIR . '/') || str_contains($relative, '..') || str_contains($relative, "\0"))
		{
			return null;
		}

		$folder = realpath(JPATH_ROOT . '/' . self::IMAGE_DIR);
		$file   = realpath(JPATH_ROOT . '/' . $relative);

		if ($folder === false || $file === false || !is_file($file) || !str_starts_with($file, $folder . \DIRECTORY_SEPARATOR))
		{
			return null;
		}

		return $file;
	}

	/**
	 * Only https images from Informanagement, at the requested width; optionally as JPEG
	 * (a 2.8 MB PNG illustration becomes ±200 kB).
	 *
	 * @param   string  $url    The image URL from the feed.
	 * @param   int     $width  Requested width in pixels.
	 * @param   bool    $jpeg   Whether to request the image as JPEG.
	 *
	 * @return  ?string  The URL to download, or null when the image is not from Informanagement over https.
	 *
	 * @since   1.0.0
	 */
	private function imageUrl(string $url, int $width, bool $jpeg): ?string
	{
		$parts = parse_url($url);
		$host  = strtolower($parts['host'] ?? '');

		if (($parts['scheme'] ?? '') !== 'https' || ($host !== 'informanagement.com' && !str_ends_with($host, '.informanagement.com')))
		{
			return null;
		}

		parse_str($parts['query'] ?? '', $query);
		$query['width'] = max(200, min(2400, $width));

		if ($jpeg)
		{
			$query['format']  = 'jpg';
			$query['quality'] = 80;
		}

		return 'https://' . $host . ($parts['path'] ?? '') . '?' . http_build_query($query);
	}

	/**
	 * The URL of the list or detail feed.
	 *
	 * @param   string                     $location  Informanagement location (nl, uk, de).
	 * @param   array<string, int|string>  $query     Query parameters for this request.
	 * @param   string                     $template  Informanagement template (sjabloon).
	 *
	 * @return  string  The feed URL.
	 *
	 * @since   1.0.0
	 */
	private function feedUrl(string $location, array $query, string $template): string
	{
		return \sprintf(self::FEED, $location) . '?' . http_build_query($query + ['forwp' => 1, 'sjabloon' => $template]);
	}

	/**
	 * Fetches a feed.
	 *
	 * @param   string  $url  The feed URL.
	 *
	 * @return  string  The response body.
	 *
	 * @throws  FeedUnavailableException  When Informanagement can't be reached or answers with another status than 200.
	 *
	 * @since   1.0.0
	 */
	private function fetch(string $url): string
	{
		try
		{
			$response = $this->http()->get($url, [], 30);
		}
		catch (\Throwable $e)
		{
			throw new FeedUnavailableException('Informanagement niet bereikbaar: ' . $e->getMessage(), 0, $e);
		}

		if ($response->getStatusCode() !== 200)
		{
			throw new FeedUnavailableException('Informanagement antwoordde met HTTP ' . $response->getStatusCode() . '.');
		}

		return (string) $response->getBody();
	}

	/**
	 * An HTTP client with the Joomla user agent.
	 *
	 * @return  \Joomla\Http\Http
	 *
	 * @since   1.0.0
	 */
	private function http(): \Joomla\Http\Http
	{
		$options = new Registry;
		$options->set('userAgent', (new Version)->getUserAgent('Joomla', true, false));

		return $this->httpFactory->getHttp($options);
	}

	/**
	 * The HTML filter for the article text: only the allowed tags and attributes, no empty paragraphs.
	 *
	 * @return  \Closure(string): string
	 *
	 * @since   1.0.0
	 */
	private function sanitizer(): \Closure
	{
		$filter = InputFilter::getInstance(self::TAGS, self::ATTRS, InputFilter::ONLY_ALLOW_DEFINED_TAGS, InputFilter::ONLY_ALLOW_DEFINED_ATTRIBUTES);

		return static function (string $html) use ($filter): string {
			$html = (string) $filter->clean($html, 'html');

			// Empty paragraphs the feed editor leaves behind.
			return trim((string) preg_replace('#<p>(\s|&nbsp;|\x{00A0})*</p>\s*#u', '', $html));
		};
	}

	/**
	 * The Super User with the lowest id, as author when the task sets none.
	 *
	 * @return  integer  The user id, or 0 when there is none.
	 *
	 * @since   1.0.0
	 */
	private function fallbackAuthor(): int
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName('m.user_id'))
			->from($this->db->quoteName('#__user_usergroup_map', 'm'))
			->where($this->db->quoteName('m.group_id') . ' = 8')
			->order($this->db->quoteName('m.user_id'));

		return (int) $this->db->setQuery($query, 0, 1)->loadResult();
	}
}
