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
 * Turns a FeedItem into the fields of a Joomla article. Free of Joomla, so it can be tested on its own.
 *
 * @since  1.0.0
 */
final class ArticleMapper
{
	private const SOURCE_LABELS = ['nl' => 'Bron', 'uk' => 'Source', 'de' => 'Quelle'];

	/**
	 * HTML filter for the article text.
	 *
	 * @var    \Closure(string): string
	 * @since  1.0.0
	 */
	private \Closure $sanitize;

	/**
	 * Constructor.
	 *
	 * @param   string                     $location    Informanagement location (nl, uk, de); sets the source label.
	 * @param   bool                       $showSource  Whether to add the source line to the article.
	 * @param   ?callable(string): string  $sanitize    HTML filter for the article text.
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		private readonly string $location = 'nl',
		private readonly bool $showSource = true,
		?callable $sanitize = null,
	) {
		$this->sanitize = $sanitize !== null ? $sanitize(...) : static fn (string $html): string => $html;
	}

	/**
	 * Maps a feed item to article fields.
	 *
	 * @param   FeedItem  $item  The item from the detail feed.
	 *
	 * @return  array{title: string, introtext: string, fulltext: string, created: string, metadesc: string}
	 *
	 * @since   1.0.0
	 */
	public function map(FeedItem $item): array
	{
		$html = ($this->sanitize)($item->description);

		[$intro, $full] = $this->split($html);

		$source = $this->source($item);

		if ($source !== '')
		{
			if ($full === '')
			{
				$intro .= "\n" . $source;
			}
			else
			{
				$full .= "\n" . $source;
			}
		}

		return [
			'title'     => $item->title,
			'introtext' => $intro,
			'fulltext'  => $full,
			'created'   => $item->published->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
			'metadesc'  => $this->metaDescription($intro !== '' ? $intro : $item->introduction),
		];
	}

	/**
	 * First paragraph as intro text (blog and news overviews), the rest as full text.
	 *
	 * @param   string  $html  The sanitised article text.
	 *
	 * @return  array{0: string, 1: string}  Intro text and full text.
	 *
	 * @since   1.0.0
	 */
	private function split(string $html): array
	{
		$html = trim($html);
		$end  = stripos($html, '</p>');

		if (!str_starts_with(strtolower($html), '<p') || $end === false)
		{
			return [$html, ''];
		}

		$end += 4;

		return [substr($html, 0, $end), trim(substr($html, $end))];
	}

	/**
	 * The source line (agency, character, number, date) as a paragraph.
	 *
	 * @param   FeedItem  $item  The item from the detail feed.
	 *
	 * @return  string  HTML, or an empty string when switched off or there is nothing to show.
	 *
	 * @since   1.0.0
	 */
	private function source(FeedItem $item): string
	{
		if (!$this->showSource)
		{
			return '';
		}

		$parts = [
			$item->agency,
			$item->character,
			$item->number,
			// A calendar date with its own offset (00:00 +01:00): converting it would shift the day.
			$item->sourceDate?->format('d-m-Y') ?? '',
		];
		$parts = array_filter($parts, static fn (string $part): bool => $part !== '');

		if ($parts === [])
		{
			return '';
		}

		$label = self::SOURCE_LABELS[$this->location] ?? self::SOURCE_LABELS['nl'];
		$text  = htmlspecialchars($label . ': ' . implode(' | ', $parts), \ENT_QUOTES | \ENT_HTML5, 'UTF-8', false);

		return '<p class="c-im-source">' . $text . '</p>';
	}

	/**
	 * Plain text of at most 160 characters, cut at a word.
	 *
	 * @param   string  $html  Text to describe the article with.
	 *
	 * @return  string  The meta description.
	 *
	 * @since   1.0.0
	 */
	private function metaDescription(string $html): string
	{
		$text = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
		$text = trim((string) preg_replace('/\s+/u', ' ', $text));

		if (mb_strlen($text) <= 160)
		{
			return $text;
		}

		$cut = mb_substr($text, 0, 157);
		$cut = mb_substr($cut, 0, (int) mb_strrpos($cut, ' ')) ?: $cut;

		return rtrim($cut, " ,;:") . '…';
	}
}
