<?php

declare(strict_types=1);

/**
 * FeedParser and FeedItem: list and detail feed, dates, hash, invalid XML and XXE.
 * Run: php tests/unit/FeedParserTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Informanagement\Feed\FeedParser;
use HKweb\Plugin\Task\Informanagement\Feed\FeedUnavailableException;

$parser = new FeedParser;
$items  = $parser->parse(fixture('list.xml'));

// Items without id are left out; a revision points to its original.
check('items without id are skipped', \count($items), 2);
check('id', $items[0]->id, 69253);
check('source id of an original', $items[0]->sourceId, 69253);
check('source id of a revision', $items[1]->sourceId, 69225);
check('revision is detected', $items[1]->isRevision(), true);
check('original is no revision', $items[0]->isRevision(), false);

// Entities in the title are decoded, HTML in the description is kept.
check('title decoded', $items[0]->title, "Kamer stemt in met 'Belastingplan' & novelle");
check('description keeps HTML', str_contains($items[0]->description, '<h4>Tussenkop</h4>'), true);
check('category', $items[0]->category, 'Belastingplan');

// Dates are read with their own offset: 05:00 +01:00 is 04:00 UTC.
check('published in UTC', $items[0]->published->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), '2026-11-13 04:00:00');
check('source date', $items[0]->sourceDate?->format('Y-m-d'), '2026-11-12');
check('empty source date is null', $items[1]->sourceDate, null);

check('image URL forced to https', $items[1]->image, 'https://cdn.informanagement.com/imglib/6ee4968a.png?width=800');

// The detail feed adds the source fields.
$detail = $parser->parse(fixture('item.xml'))[0];
check('agency', $detail->agency, 'Ministerie van Financiën');
check('character', $detail->character, 'wetswijziging');
check('number', $detail->number, '');

// The hash ignores detail-only fields, so list and detail compare equal on the same content.
check('hash is sha256', \strlen($items[0]->hash()), 64);
check('different description, different hash', $items[0]->hash() !== $detail->hash(), true);
check('hash is stable', $items[0]->hash(), $parser->parse(fixture('list.xml'))[0]->hash());

// Invalid XML (an error or maintenance page) counts as Informanagement being unavailable.
check('invalid XML throws FeedUnavailableException', thrown(static fn () => $parser->parse('<html>Service unavailable')), FeedUnavailableException::class);

// External entities are not loaded (XXE).
$xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
	. '<rss><channel><item><id>1</id><title>&x;</title><pubDate>Fri, 13 Nov 2026 05:00:00 +0100</pubDate></item></channel></rss>';
check('external entities not loaded', str_contains($parser->parse($xml)[0]->title, 'root:'), false);

finish('FeedParser');
