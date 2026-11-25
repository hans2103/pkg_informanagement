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
 * Reads the RSS of the Informanagement custom feed into FeedItem objects.
 *
 * @since  1.0.0
 */
final class FeedParser
{
	/**
	 * Parses a list or detail feed.
	 *
	 * @param   string  $xml  The RSS as received.
	 *
	 * @return  FeedItem[]  Items without id or date are left out.
	 *
	 * @throws  FeedUnavailableException  When the XML can't be read (an error or maintenance page).
	 *
	 * @since   1.0.0
	 */
	public function parse(string $xml): array
	{
		$previous = libxml_use_internal_errors(true);

		try
		{
			// LIBXML_NONET: never fetch anything; without LIBXML_NOENT entities are not substituted.
			$rss = simplexml_load_string($xml, \SimpleXMLElement::class, \LIBXML_NONET | \LIBXML_NOCDATA);
		} finally
		{
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}

		if (!$rss instanceof \SimpleXMLElement || !isset($rss->channel))
		{
			throw new FeedUnavailableException('Geen geldige Informanagement-feed ontvangen.');
		}

		$items = [];

		foreach ($rss->channel->item as $node)
		{
			$item = $this->item($node);

			if ($item !== null)
			{
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * One <item> as FeedItem.
	 *
	 * @param   \SimpleXMLElement  $node  The <item> element.
	 *
	 * @return  ?FeedItem  Null when the item has no id or no valid publication date.
	 *
	 * @since   1.0.0
	 */
	private function item(\SimpleXMLElement $node): ?FeedItem
	{
		$id        = (int) $this->text($node, 'id');
		$published = $this->date($this->text($node, 'pubDate'));

		if ($id < 1 || $published === null)
		{
			return null;
		}

		$sourceId = (int) $this->text($node, 'sourceArticleId');
		$image    = $this->text($node, 'image');

		if (str_starts_with($image, 'http://'))
		{
			$image = 'https://' . substr($image, 7);
		}

		return new FeedItem(
			id: $id,
			sourceId: $sourceId > 0 ? $sourceId : $id,
			title: $this->plain($this->text($node, 'title')),
			description: trim($this->text($node, 'description')),
			introduction: $this->plain($this->text($node, 'introduction')),
			category: $this->plain($this->text($node, 'category')),
			image: $image,
			published: $published,
			sourceDate: $this->date($this->text($node, 'date')),
			agency: $this->plain($this->text($node, 'agency')),
			character: $this->plain($this->text($node, 'character')),
			number: $this->plain($this->text($node, 'number')),
		);
	}

	/**
	 * Text of a child element.
	 *
	 * @param   \SimpleXMLElement  $node  The parent element.
	 * @param   string             $name  Name of the child element.
	 *
	 * @return  string  The text, or an empty string when the element is missing.
	 *
	 * @since   1.0.0
	 */
	private function text(\SimpleXMLElement $node, string $name): string
	{
		return isset($node->{$name}) ? (string) $node->{$name} : '';
	}

	/**
	 * Titles and labels arrive with HTML entities inside CDATA (&#39;, &amp;).
	 *
	 * @param   string  $value  Text with tags and entities.
	 *
	 * @return  string  Plain text with collapsed whitespace.
	 *
	 * @since   1.0.0
	 */
	private function plain(string $value): string
	{
		$value = html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

		return trim((string) preg_replace('/\s+/u', ' ', $value));
	}

	/**
	 * Reads an RSS date (RFC 822), or any format DateTimeImmutable understands.
	 *
	 * @param   string  $value  The date as text.
	 *
	 * @return  ?\DateTimeImmutable  Null when empty or unreadable.
	 *
	 * @since   1.0.0
	 */
	private function date(string $value): ?\DateTimeImmutable
	{
		if (trim($value) === '')
		{
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat(\DATE_RSS, trim($value));

		if ($date === false)
		{
			try
			{
				$date = new \DateTimeImmutable($value);
			}
			catch (\Exception)
			{
				return null;
			}
		}

		return $date;
	}
}
