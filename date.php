<?php

function formatBytes(int $bytes): string {
	$units = ['B', 'KB', 'MB', 'GB'];
	$i = 0;
	while ($bytes >= 1024 && $i < count($units) - 1) {
		$bytes /= 1024;
		$i++;
	}
	return sprintf("%.2f %s", $bytes, $units[$i]);
}

function benchmark(callable $fn, string $label): array {
	gc_collect_cycles();
	gc_mem_caches();
	if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
	$startMem = memory_get_peak_usage();
	$start = hrtime(true);
	$result = $fn();
	$end = hrtime(true);
	$memUsed = memory_get_peak_usage() - $startMem;
	return [
		'label'    => $label,
		'time_ms'  => ($end - $start) / 1e6,
		'mem_used' => $memUsed,
		'result'   => $result,
	];
}

/**
 * Generate deterministic Apache-style log date strings over multiple days.
 */
function generateApacheDates(int $days = 5, int $perDay = 1000, string $startDate = '2025-10-25 00:00:00'): array {
	$dates = [];
	$startTs = strtotime($startDate);

	for ($d = 0; $d < $days; $d++) {
		$dayBase = $startTs + $d * 86400;
		for ($i = 0; $i < $perDay; $i++) {
			// Spread seconds evenly across the day
			$sec = intval($i * (86400 / $perDay));
			$ts = $dayBase + $sec;
			$dates[] = gmdate('d/M/Y:H:i:s O', $ts);
		}
	}

	return $dates;
}

/**
 * Manual parser for Apache-style date format.
 *
 * Expected format: "25/Oct/2025:12:34:56 +0000"
 * Returns Unix timestamp (UTC) or false on obvious format failure.
 */
function parseApacheManual(string $s): int|false
{
	// Expected format: 25/Oct/2025:12:34:56 +0000
	if (strlen($s) !== 26) {
		return false;
	}

	static $months = [
		'Jan' => 1,'Feb' => 2,'Mar' => 3,'Apr' => 4,'May' => 5,'Jun' => 6,
		'Jul' => 7,'Aug' => 8,'Sep' => 9,'Oct' => 10,'Nov' => 11,'Dec' => 12
	];

	$mon   = substr($s, 3, 3);
	$month = $months[$mon] ?? 0;

	if ($month === 0) {
		return false;
	}

	// Using fixed string offsets here is much faster than a regex
	$d   = $s[0] * 10 + $s[1];
	$y   = $s[7] * 1000 + $s[8] * 100 + $s[9] * 10 + $s[10];
	$h   = $s[12] * 10 + $s[13];
	$min = $s[15] * 10 + $s[16];
	$sec = $s[18] * 10 + $s[19];

	$tzSign = $s[21] === '-' ? -1 : 1;
	$tzH = $s[22] * 10 + $s[23];
	$tzM = $s[24] * 10 + $s[25];
	$tzOffset = $tzSign * ($tzH * 3600 + $tzM * 60);

	return gmmktime($h, $min, $sec, $month, $d, $y) - $tzOffset;
}

/**
 * Optimized cached version — caches per-day base and per-second offsets.
 *
 * Expected format: "25/Oct/2025:12:34:56 +0000"
 * Returns Unix timestamp (UTC) or false on obvious format failure.
 */
function parseApacheManualCached(string $s): int|false {
	static $day_cache = [];
	static $time_cache = [];

	// Expected format: 25/Oct/2025:12:34:56 +0000
	if (strlen($s) !== 26) {
		return false;
	}

	// Using fixed string offsets here is much faster than a regex
	$day_key  = substr_replace($s, '', 11, 10); // remove ":HH:MM:SS "

	if (!isset($day_cache[$day_key])) {
		static $months = [
			'Jan' => 1,'Feb' => 2,'Mar' => 3,'Apr' => 4,'May' => 5,'Jun' => 6,
			'Jul' => 7,'Aug' => 8,'Sep' => 9,'Oct' => 10,'Nov' => 11,'Dec' => 12
		];

		$mon   = substr($s, 3, 3);   // month: "Oct"
		$month = $months[$mon] ?? 0;

		if ($month === 0) {
			return false;
		}

		$d   = $s[0] * 10 + $s[1];
		$y   = $s[7] * 1000 + $s[8] * 100 + $s[9] * 10 + $s[10];
		$tzSign = $s[21] === '-' ? -1 : 1;
		$tzH = $s[22] * 10 + $s[23];
		$tzM = $s[24] * 10 + $s[25];
		$tzOffset = $tzSign * ($tzH * 3600 + $tzM * 60);
		$day_cache[$day_key] = gmmktime(0, 0, 0, $month, $d, $y) - $tzOffset;

		if ($day_cache[$day_key] === false) {
			return false;
		}
	}

	// hours:   0–23 (needs 5 bits)
	// minutes: 0–59 (needs 6 bits)
	// seconds: 0–59 (needs 6 bits)
	$h = $s[12] * 10 + $s[13];
	$m = $s[15] * 10 + $s[16];
	$sec = $s[18] * 10 + $s[19];
	$time_key = ($h << 12) | ($m << 6) | $sec;

	if (!isset($time_cache[$time_key])) {
		$time = $h * 3600 + $m * 60 + $sec;
		$time_cache[$time_key] = $time;
	}

	return $day_cache[$day_key] + $time_cache[$time_key];
}

/**
 * Cached DateTime approach — caches per-day base DateTime and seconds offsets.
 *
 * Expected format: "25/Oct/2025:12:34:56 +0000"
 * Returns Unix timestamp (UTC) or false on obvious format failure.
 */
function parseApacheDateTimeCached(string $s): int|false {
	static $day_cache = [];
	static $time_cache = [];

	// Expected format: 25/Oct/2025:12:34:56 +0000
	if (strlen($s) !== 26) {
		return false;
	}

	// Using fixed string offsets here is much faster than a regex
	$day_key  = substr_replace($s, '', 11, 10); // remove ":HH:MM:SS "

	if (!isset($day_cache[$day_key])) {
		$dt = DateTime::createFromFormat('!d/M/YO', $day_key);

		if ($dt === false) {
			return false;
		}

		$day_cache[$day_key] = $dt->getTimestamp();
	}

	// hours:   0–23 (needs 5 bits)
	// minutes: 0–59 (needs 6 bits)
	// seconds: 0–59 (needs 6 bits)
	$h = $s[12] * 10 + $s[13];
	$m = $s[15] * 10 + $s[16];
	$sec = $s[18] * 10 + $s[19];
	$time_key = ($h << 12) | ($m << 6) | $sec;

	if (!isset($time_cache[$time_key])) {
		$time = $h * 3600 + $m * 60 + $sec;
		$time_cache[$time_key] = $time;
	}

	return $day_cache[$day_key] + $time_cache[$time_key];
}

/**
 * Cached DateTimeImmutable approach.
 *
 * Expected format: "25/Oct/2025:12:34:56 +0000"
 * Returns Unix timestamp (UTC) or false on obvious format failure.
 */
function parseApacheDateTimeImmCached(string $s): int|false {
	static $day_cache = [];
	static $time_cache = [];

	// Expected format: 25/Oct/2025:12:34:56 +0000
	if (strlen($s) !== 26) {
		return false;
	}

	// Using fixed string offsets here is much faster than a regex
	$day_key  = substr_replace($s, '', 11, 10); // remove ":HH:MM:SS "

	if (!isset($day_cache[$day_key])) {
		$dti = DateTimeImmutable::createFromFormat('!d/M/YO', $day_key);

		if ($dti === false) {
			return false;
		}

		$day_cache[$day_key] = $dti->getTimestamp();
	}

	// hours:   0–23 (needs 5 bits)
	// minutes: 0–59 (needs 6 bits)
	// seconds: 0–59 (needs 6 bits)
	$h = $s[12] * 10 + $s[13];
	$m = $s[15] * 10 + $s[16];
	$sec = $s[18] * 10 + $s[19];
	$time_key = ($h << 12) | ($m << 6) | $sec;

	if (!isset($time_cache[$time_key])) {
		$time = $h * 3600 + $m * 60 + $sec;
		$time_cache[$time_key] = $time;
	}

	return $day_cache[$day_key] + $time_cache[$time_key];
}

/**
 * Cached version of strtotime().
 */
function parseStrtotimeCached(string $s): int|false {
	static $cache = [];
	if (isset($cache[$s])) return $cache[$s];

	$ts = strtotime($s);
	if ($ts !== false) $cache[$s] = $ts;

	return $ts;
}

/**
 * Run all benchmarks with correctness checking.
 */
function runBenchmarks(int $iterations, array $inputs): void {
	echo "\n=== Apache Log Date Parsing Benchmark ({$iterations} iterations per method) ===\n";
	echo "Sample input: " . $inputs[0] . " ... (" . count($inputs) . " dates)\n";

	$results = [];
	$reference = [];

	foreach ($inputs as $s) {
		$reference[$s] = strtotime($s);
	}

	$parsers = [
		'strtotime()'                     => fn($s) => strtotime($s),
		'parseStrtotimeCached()'          => fn($s) => parseStrtotimeCached($s),
		'DateTime::createFromFormat()'    => fn($s) => DateTime::createFromFormat('d/M/Y:H:i:s O', $s)?->getTimestamp(),
		'DateTimeImm::createFromFormat()' => fn($s) => DateTimeImmutable::createFromFormat('d/M/Y:H:i:s O', $s)?->getTimestamp(),
		'parseApacheManual()'             => fn($s) => parseApacheManual($s),
		'parseApacheManualCached()'       => fn($s) => parseApacheManualCached($s),
		'parseApacheDateTimeCached()'     => fn($s) => parseApacheDateTimeCached($s),
		'parseApacheDateTimeImmCached()'  => fn($s) => parseApacheDateTimeImmCached($s),
	];

	foreach ($parsers as $label => $parser) {
		$results[] = benchmark(function() use ($parser, $inputs, $iterations, $reference) {
			$errors = 0;
			for ($i = 0; $i < $iterations; $i++) {
				foreach ($inputs as $s) {
					$v = $parser($s);
					if ($v === false || $v !== $reference[$s]) {
						$errors++;
					}
				}
			}
			return $errors;
		}, $label);
	}

	echo str_repeat('-', 95) . "\n";
	printf("%-32s | %-12s | %-15s | %-8s\n", 'Method', 'Time (ms)', 'Memory Used', 'Errors');
	echo str_repeat('-', 95) . "\n";

	foreach ($results as $r) {
		printf("%-32s | %10.3f ms | %15s | %8d\n",
			$r['label'],
			$r['time_ms'],
			formatBytes($r['mem_used']),
			$r['result']
		);
	}
	echo str_repeat('-', 95) . "\n";
}

// Increase memory to 2GB
ini_set('memory_limit', '2G');

// Generate random test data
$inputs = generateApacheDates(5, 100000);

runBenchmarks(1, $inputs);
