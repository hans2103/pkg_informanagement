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
 * One article from the Informanagement feed.
 *
 * The list feed (command=rss) carries everything except the source fields agency, character and
 * number; those only come with the detail feed (command=viewitem).
 *
 * @since  1.0.0
 */
final readonly class FeedItem
{
	/**
	 * The fields are public and readonly: a FeedItem is a value object.
	 *
	 * @param   int                  $id            Feed id of this article (a revision gets a new one).
	 * @param   int                  $sourceId      Id of the original article; equal to $id when it is no revision.
	 * @param   string               $title         Plain-text title.
	 * @param   string               $description   Article text as HTML.
	 * @param   string               $introduction  Plain-text introduction.
	 * @param   string               $category      Informanagement section.
	 * @param   string               $image         Image URL (https).
	 * @param   \DateTimeImmutable   $published     Publication date.
	 * @param   ?\DateTimeImmutable  $sourceDate    Calendar date of the source, when known.
	 * @param   string               $agency        Source agency (detail feed only).
	 * @param   string               $character     Source character (detail feed only).
	 * @param   string               $number        Source number (detail feed only).
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		public int $id,
		public int $sourceId,
		public string $title,
		public string $description,
		public string $introduction,
		public string $category,
		public string $image,
		public \DateTimeImmutable $published,
		public ?\DateTimeImmutable $sourceDate,
		public string $agency = '',
		public string $character = '',
		public string $number = '',
	) {
	}

	/**
	 * Informanagement gives an amended article a new id; sourceArticleId points to the original.
	 *
	 * @return  boolean  True when this item replaces an earlier article.
	 *
	 * @since   1.0.0
	 */
	public function isRevision(): bool
	{
		return $this->sourceId !== $this->id;
	}

	/**
	 * Fingerprint of the fields the list feed carries, to see whether an article changed
	 * without fetching its detail feed.
	 *
	 * @return  string  SHA-256 hash in hexadecimal.
	 *
	 * @since   1.0.0
	 */
	public function hash(): string
	{
		$fields = [
			$this->sourceId,
			$this->title,
			$this->description,
			$this->category,
			$this->image,
			$this->published->format(\DATE_ATOM),
			$this->sourceDate?->format(\DATE_ATOM) ?? '',
		];

		return hash('sha256', implode("\x1F", $fields));
	}
}
