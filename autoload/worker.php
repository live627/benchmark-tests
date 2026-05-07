<?php

declare(strict_types=1);

[$_, $mode, $count, $random] = $argv;

$count = (int)$count;
$random = $random === '1';

$baseNamespace = 'App\\Generated\\';
$baseDir = __DIR__ . '/generated/App/Generated';

// ---- Build class list ----
$classes = [];
for ($i = 1; $i <= $count; $i++) {
	$group = 'Group' . (int) ceil($i / 100);
	$classes[] = $baseNamespace . $group . '\\Class' . $i;
}

// ---- Loader selection ----
switch ($mode) {
	case 'custom_require':
		require __DIR__ . '/custom_loader.php';
		registerCustomLoader($baseNamespace, $baseDir, false);
		break;

	case 'custom_once':
		require __DIR__ . '/custom_loader.php';
		registerCustomLoader($baseNamespace, $baseDir, true);
		break;

	case 'classlist':
		require __DIR__ . '/custom_loader.php';
		registerCustomLoader($baseNamespace, $baseDir, false, require __DIR__ . '/classmap.php');
		break;

	case 'classlist-once':
		require __DIR__ . '/custom_loader.php';
		registerCustomLoader($baseNamespace, $baseDir, true, require __DIR__ . '/classmap.php');
		break;

	case 'classlist-authoritative':
		require __DIR__ . '/custom_loader.php';
		registerCustomLoader($baseNamespace, $baseDir, false, require __DIR__ . '/classmap.php', true);
		break;

	case 'classlist-authoritative-once':
		require __DIR__ . '/custom_loader.php';
		registerCustomLoader($baseNamespace, $baseDir, true, require __DIR__ . '/classmap.php', true);
		break;

	case 'composer':
		require __DIR__ . '/vendor/autoload.php';
		break;

	case 'composer-optimized':
		$loader = require __DIR__ . '/vendor/autoload.php';
		$loader->addClassMap(require __DIR__ . '/classmap.php');
		break;

	case 'composer-authoritative':
		$loader = require __DIR__ . '/vendor/autoload.php';
		$loader->addClassMap(require __DIR__ . '/classmap.php');
		$loader->setClassMapAuthoritative(true);
		break;

	default:
		fwrite(STDERR, "Unknown mode \"$mode\"\n");
		exit(1);
}

// ---- Build class list ----
$classes = [];
for ($i = 1; $i <= $count; $i++) {
	$group = 'Group' . (int) ceil($i / 100);
	$classes[] = $baseNamespace . $group . '\\Class' . $i;
}

if ($random) {
	shuffle($classes);
}

// ---- Optional OPCache reset (cold test) ----
if (function_exists('opcache_reset')) {
	opcache_reset();
}
clearstatcache();

// ---- Benchmark ----
$start = hrtime(true);

foreach ($classes as $class) {
	class_exists($class);
	class_exists($class . '_MISS');
}

$timeNs = hrtime(true) - $start;

echo $timeNs . PHP_EOL; // return raw nanoseconds