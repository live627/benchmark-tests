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
 */
function parseApacheManual(string $s): int|false {
	static $months = [
		'Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,
		'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12
	];
	if (!preg_match(
		'/^(\d{2})\/([A-Za-z]{3})\/(\d{4}):(\d{2}):(\d{2}):(\d{2}) ([\+\-]\d{4})$/',
		$s, $m
	)) return false;

	[$_, $d, $mon, $y, $h, $i, $sec, $tz] = $m;
	$month = $months[$mon] ?? 0;
	if (!$month) return false;

	$tzSign = $tz[0] === '-' ? -1 : 1;
	$tzHours = (int)substr($tz, 1, 2);
	$tzMins = (int)substr($tz, 3, 2);
	$tzOffset = $tzSign * ($tzHours * 3600 + $tzMins * 60);

	return gmmktime($h, $i, $sec, $month, $d, $y) - $tzOffset;
}

/**
 * Optimized cached version — caches per-day base and per-second offsets.
 */
function parseApacheManualCached(string $s): int|false {
	static $months = [
		'Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,
		'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12
	];
	static $day_cache = [];
	static $time_cache = [];

	// Expected format: 25/Oct/2025:12:34:56 +0000
	if (strlen($s) !== 26) {
		return false;
	}

	// Using fixed string offsets here is much faster than a regex
	$d    = substr($s, 0, 2);   // day: "25"
	$mon  = substr($s, 3, 3);   // month: "Oct"
	$y    = substr($s, 7, 4);   // year: "2025"
	$h    = substr($s, 12, 2);  // hour: "12"
	$i    = substr($s, 15, 2);  // minute: "34"
	$sec  = substr($s, 18, 2);  // second: "56"
	$tz   = substr($s, 21, 5);  // timezone: "+0000"

	$monthNum = $months[$mon] ?? 0;
	if ($monthNum === 0) return false;

	$day_key = $y + $monthNum + $d;
	$time_key = $h * 3600 + $i * 60 + $sec;

	if (!isset($day_cache[$day_key])) {
		$tzSign = $tz[0] === '-' ? -1 : 1;
		$tzHours = (int)substr($tz, 1, 2);
		$tzMins = (int)substr($tz, 3, 2);
		$tzOffset = $tzSign * ($tzHours * 3600 + $tzMins * 60);
		$day_cache[$day_key] = gmmktime(0, 0, 0, $monthNum, $d, $y) - $tzOffset;
	}

	if (!isset($time_cache[$time_key])) {
		$time_cache[$time_key] = $time_key;
	}

	return $day_cache[$day_key] + $time_cache[$time_key];
}

/**
 * Cached DateTime approach — caches per-day base DateTime and seconds offsets.
 */
function parseApacheDateTimeCached(string $s): int|false {
	static $day_cache = [];
	static $time_cache = [];

	// Expected format: 25/Oct/2025:12:34:56 +0000
	if (strlen($s) !== 26) {
		return false;
	}

	// Using fixed string offsets here is much faster than a regex
	$day_str = substr($s, 0, 11);   // "25/Oct/2025"
	$h      = substr($s, 12, 2);   // "12"
	$i      = substr($s, 15, 2);   // "34"
	$sec    = substr($s, 18, 2);   // "56"
	$tz     = substr($s, 21, 5);   // "+0000"

	$day_key = "$day_str $tz";
	$time_key = $h * 3600 + $i * 60 + $sec;

	if (!isset($day_cache[$day_key])) {
		$day_cache[$day_key] = DateTime::createFromFormat('d/M/Y H:i:s O', "$day_str 00:00:00 $tz")->getTimestamp();
		if (!$day_cache[$day_key]) return false;
	}

	if (!isset($time_cache[$time_key])) {
		$time_cache[$time_key] = $time_key;
	}

	return $day_cache[$day_key] + $time_cache[$time_key];
}

/**
 * Cached DateTimeImmutable approach.
 */
function parseApacheDateTimeImmCached(string $s): int|false {
	static $day_cache = [];
	static $time_cache = [];

	// Expected format: 25/Oct/2025:12:34:56 +0000
	if (strlen($s) !== 26) {
		return false;
	}

	// Using fixed string offsets here is much faster than a regex
	$day_str = substr($s, 0, 11);   // "25/Oct/2025"
	$h      = substr($s, 12, 2);   // "12"
	$i      = substr($s, 15, 2);   // "34"
	$sec    = substr($s, 18, 2);   // "56"
	$tz     = substr($s, 21, 5);   // "+0000"

	$day_key = $day_str . $tz;
	$time_key = $h * 3600 + $i * 60 + $sec;

	if (!isset($day_cache[$day_key])) {
		$day_cache[$day_key] = DateTimeImmutable::createFromFormat('d/M/Y H:i:s O', $day_str . ' 00:00:00 ' . $tz)->getTimestamp();
		if (!$day_cache[$day_key]) {
			return false;
		}
	}

	if (!isset($time_cache[$time_key])) {
		$time_cache[$time_key] = $time_key;
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
