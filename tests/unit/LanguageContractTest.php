<?php

declare(strict_types=1);

/**
 * Language contract: en-GB and nl-NL have the same keys in every ini file, and every label and
 * description used in a manifest or form exists in both languages.
 * Run: php tests/unit/LanguageContractTest.php
 */

require __DIR__ . '/../bootstrap.php';

$src = \dirname(__DIR__, 2) . '/Src';

/**
 * The keys of an ini file.
 *
 * @return  list<string>
 */
function iniKeys(string $file): array
{
	$keys = array_keys((array) parse_ini_file($file, false, INI_SCANNER_RAW));
	sort($keys);

	return $keys;
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
$known    = [];

foreach ($iterator as $file) {
	$path = (string) $file;

	if (!str_ends_with($path, '.ini') || !str_contains($path, '/en-GB/')) {
		continue;
	}

	$dutch = str_replace('/en-GB/', '/nl-NL/', $path);
	$name  = substr($path, \strlen($src) + 1);

	check("nl-NL exists for $name", is_file($dutch), true);

	if (is_file($dutch)) {
		check("same keys in $name", iniKeys($path), iniKeys($dutch));
	}

	$known = array_merge($known, iniKeys($path));
}

// Every PLG_/MOD_/PKG_ constant used in an XML file has a string.
foreach ($iterator as $file) {
	$path = (string) $file;

	if (!str_ends_with($path, '.xml')) {
		continue;
	}

	preg_match_all('/\b((?:PLG|MOD|PKG)_[A-Z0-9_]+)\b/', (string) file_get_contents($path), $matches);

	foreach (array_unique($matches[1]) as $constant) {
		check("string for $constant", \in_array($constant, $known, true), true);
	}
}

finish('LanguageContract');
