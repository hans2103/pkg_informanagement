<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the plain-PHP unit tests in tests/unit/ (no PHPUnit).
 * Loads the task plugin classes (PSR-4) and defines check(), fixture() and finish().
 * The feed classes are Joomla-free, so no Joomla installation is needed.
 */

\define('_JEXEC', 1);

const PLUGIN_DIR = __DIR__ . '/../Src/plugins/task/informanagement';

spl_autoload_register(static function (string $class): void {
	$prefix = 'HKweb\\Plugin\\Task\\Informanagement\\';

	if (str_starts_with($class, $prefix)) {
		$file = PLUGIN_DIR . '/src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

		if (is_file($file)) {
			require $file;
		}
	}
});

$failures = 0;

function check(string $label, mixed $actual, mixed $expected): void
{
	global $failures;

	if ($actual === $expected) {
		echo "  ok   $label\n";

		return;
	}

	$failures++;
	echo "  FAIL $label\n       expected: " . var_export($expected, true) . "\n       actual:   " . var_export($actual, true) . "\n";
}

/**
 * The class of the exception the callable throws, or null when it throws nothing.
 */
function thrown(callable $callable): ?string
{
	try {
		$callable();
	} catch (\Throwable $e) {
		return $e::class;
	}

	return null;
}

function fixture(string $name): string
{
	return (string) file_get_contents(__DIR__ . '/unit/fixtures/' . $name);
}

function finish(string $name): never
{
	global $failures;

	echo $failures === 0 ? "\nAll $name assertions passed.\n" : "\n$failures FAILURE(S) in $name.\n";
	exit($failures === 0 ? 0 : 1);
}
