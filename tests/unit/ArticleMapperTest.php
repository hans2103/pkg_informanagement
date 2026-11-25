<?php

declare(strict_types=1);

/**
 * ArticleMapper: intro and full text, dates, source line, meta description, sanitizer.
 * Run: php tests/unit/ArticleMapperTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Informanagement\Feed\ArticleMapper;
use HKweb\Plugin\Task\Informanagement\Feed\FeedParser;

$parser = new FeedParser;
$first  = $parser->parse(fixture('list.xml'))[0];
$second = $parser->parse(fixture('list.xml'))[1];
$detail = $parser->parse(fixture('item.xml'))[0];

// The text is split after the first paragraph.
$data = (new ArticleMapper)->map($first);
check('introtext is the first paragraph', $data['introtext'], '<p>Eerste alinea met &euro; 900 miljoen.</p>');
check('fulltext starts after it', str_starts_with($data['fulltext'], '<h4>Tussenkop</h4>'), true);
check('created is UTC', $data['created'], '2026-11-13 04:00:00');
check('meta description is plain text', $data['metadesc'], 'Eerste alinea met € 900 miljoen.');
check('meta description is short', mb_strlen($data['metadesc']) <= 160, true);

$data = (new ArticleMapper)->map($second);
check('single paragraph: introtext', $data['introtext'], '<p>Alleen een alinea.</p>');
check('single paragraph: no fulltext', $data['fulltext'], '');

// Source line: local calendar date from the feed (12 Nov 00:00 +0100 would be 11 Nov in UTC), no inline style.
$data = (new ArticleMapper)->map($detail);
$text = $data['introtext'] . $data['fulltext'];
check('source line at the end', str_ends_with($text, '<p class="c-im-source">Bron: Ministerie van Financiën | wetswijziging | 12-11-2026</p>'), true);
check('no inline style', str_contains($text, 'style='), false);
check('source date is the feed calendar date', str_contains($data['introtext'], '| 12-11-2026</p>'), true);

$data = (new ArticleMapper(showSource: false))->map($detail);
check('source line can be switched off', str_contains($data['introtext'] . $data['fulltext'], 'c-im-source'), false);

$data = (new ArticleMapper(location: 'de'))->map($detail);
check('source label follows the location', str_contains($data['introtext'], '>Quelle: '), true);

// The sanitizer is applied.
$mapper = new ArticleMapper(sanitize: static fn (string $html): string => str_replace(' border="1" cellpadding="1"', '', $html));
check('sanitizer applied', str_contains($mapper->map($first)['fulltext'], '<table><tr>'), true);

finish('ArticleMapper');
