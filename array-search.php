<?php

ini_set('memory_limit', '2048M');

/**
 * Get all user-defined functions that start with a given prefix.
 *
 * @param string $prefix
 * @return array
 */
function getFunctionsByPrefix(string $prefix): array
{
	$functions = get_defined_functions();

	return array_values(
		array_filter(
			$functions['user'],
			static function (string $fn) use ($prefix): bool {
				return stripos($fn, $prefix) === 0;
			}
		)
	);
}

function formatBytes(int $bytes): string {
	$units = ['B', 'KB', 'MB', 'GB'];
	$i = 0;
	while ($bytes >= 1024 && $i < count($units) - 1) {
		$bytes /= 1024;
		$i++;
	}
	return sprintf("%.2f %s", $bytes, $units[$i]);
}

 function benchmark(callable $fn, string $label, int $iterations = 10, int $warmup = 3): array
{
	// Warmup: allows OPcache JIT + branch prediction + allocator reuse
	for ($i = 0; $i < $warmup; $i++) {
		$fn();
	}

	$times = [];
	$memories = [];

	for ($i = 0; $i < $iterations; $i++) {
		gc_collect_cycles();
		gc_mem_caches();
		memory_reset_peak_usage();

		$mem = -memory_get_peak_usage();
		$time = -hrtime(true);

		$fn();

		$time += hrtime(true);
		$mem += memory_get_peak_usage();

		$times[] = $time;
		$memories[] = $mem;
	}

	sort($times);
	sort($memories);

	$count = count($times);
	$medianIndex = intdiv($count, 2);

	$medianTime = $times[$medianIndex];
	$medianMem = $memories[$medianIndex];

	$avgTime = array_sum($times) / $count;
	$avgMem = array_sum($memories) / $count;

	return [
		'label' => $label,
		'time' => $medianTime,
		'time_avg' => $avgTime,
		'mem' => $medianMem,
		'mem_avg' => $avgMem,
		'iterations' => $iterations,
	];
}

function search_in_array(array $a, $needle): int {
	foreach ($a as $i => $v) {
		if ($v === $needle) {
			return $i;
		}
	}
	return -1;
}

function search_array_search(array $a, $needle): int {
	$r = array_search($needle, $a, true);
	return $r === false ? -1 : $r;
}

function search_isset_map(array $a, $needle): int {
	$map = array_flip($a);
	return isset($map[$needle]) ? $map[$needle] : -1;
}

function search_isset_map_static(array $a, $needle): int {
	static $map;
	$map ??= array_flip($a);
	return isset($map[$needle]) ? $map[$needle] : -1;
}

function search_foreach_early_exit(array $a, $needle): int {
	$i = 0;
	foreach ($a as $v) {
		if ($v === $needle) {
			return $i;
		}
		$i++;
	}
	return -1;
}

function search_accumulate(array $a, $needle): bool {
    $found = 0;

    foreach ($a as $v) {
        $found |= ($v === $needle);
    }

    return (bool)$found;
}

function search_branchless_index(array $a, $needle): int {
    $pos = -1;

    foreach ($a as $i => $v) {
        $match = ($v === $needle);
        $pos = $match ? $i : $pos;
    }

    return $pos;
}

function search_chunked(array $a, $needle): bool {
    $n = count($a);

    for ($i = 0; $i < $n; $i += 4) {
        if (
            ($a[$i]   ?? null) === $needle ||
            ($a[$i+1] ?? null) === $needle ||
            ($a[$i+2] ?? null) === $needle ||
            ($a[$i+3] ?? null) === $needle
        ) {
            return true;
        }
    }

    return false;
}

function search_string(array $a, $needle): bool {
    return $exists = strpos(implode("\0", $a), $needle) !== false;
}

function search_binary(array $a, $needle): int {
	$low = 0;
	$high = count($a) - 1;

	while ($low <= $high) {
		$mid = intdiv($low + $high, 2);

		if ($a[$mid] === $needle) {
			return $mid;
		}

		if ($a[$mid] < $needle) {
			$low = $mid + 1;
		} else {
			$high = $mid - 1;
		}
	}

	return -1;
}

function search_binary_compact(array $a, $needle): int {
	$low = 0;
	$high = count($a);

	while ($low < $high) {
		$mid = ($low + $high) >> 1;

		if ($a[$mid] < $needle) {
			$low = $mid + 1;
		} else {
			$high = $mid;
		}
	}

	return ($low < count($a) && $a[$low] === $needle) ? $low : -1;
}

function search_galloping(array $a, $needle): int {
	$n = count($a);

	if ($n === 0) return -1;

	// Check first element
	if ($a[0] === $needle) return 0;

	// Find range via exponential growth
	$i = 1;
	while ($i < $n && $a[$i] < $needle) {
		$i <<= 1;
	}

	$low = $i >> 1;
	$high = min($i, $n - 1);

	// Binary search in range
	while ($low <= $high) {
		$mid = intdiv($low + $high, 2);

		if ($a[$mid] === $needle) return $mid;

		if ($a[$mid] < $needle) {
			$low = $mid + 1;
		} else {
			$high = $mid - 1;
		}
	}

	return -1;
}

function search_hybrid(array $a, $needle, int $threshold = 32): int {
	$low = 0;
	$high = count($a) - 1;

	while ($high - $low > $threshold) {
		$mid = intdiv($low + $high, 2);

		if ($a[$mid] === $needle) return $mid;

		if ($a[$mid] < $needle) {
			$low = $mid + 1;
		} else {
			$high = $mid - 1;
		}
	}

	// fallback to linear scan
	for ($i = $low; $i <= $high; $i++) {
		if ($a[$i] === $needle) return $i;
	}

	return -1;
}

function buildBlockIndex(array $a, int $blockSize = 64): array {
	$index = [];
	$n = count($a);

	for ($i = 0; $i < $n; $i += $blockSize) {
		$index[] = [
			'value' => $a[$i],
			'pos'   => $i
		];
	}

	return $index;
}

function search_block_index(array $a, array $index, $needle, int $blockSize = 64): int {
	$low = 0;
	$high = count($index) - 1;

	// Binary search on index
	while ($low <= $high) {
		$mid = intdiv($low + $high, 2);

		if ($index[$mid]['value'] <= $needle) {
			$low = $mid + 1;
		} else {
			$high = $mid - 1;
		}
	}

	$block = max(0, $low - 1);
	$start = $index[$block]['pos'];
	$end   = min($start + $blockSize, count($a));

	// Linear scan within block
	for ($i = $start; $i < $end; $i++) {
		if ($a[$i] === $needle) return $i;
	}

	return -1;
}

function randomString(int $length = 10): string
{
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$result = '';
	$max = strlen($chars) - 1;

	for ($i = 0; $i < $length; $i++) {
		$result .= $chars[random_int(0, $max)];
	}

	return $result;
}

function runSearchBenchmarks(int $n, int $iterations = 10, int $warmup = 3): void {
	echo "\n=== Array Search Benchmark for " . number_format($n) . " elements ===\n";

	// Build dataset
	$a = [];
	for ($i = 0; $i < $n; $i++) {
		$a[] = randomString();
	}

	// Needles
	$needle_start = $a[0];
	$needle_mid   = $a[intdiv($n, 2)];
	$needle_end   = $a[$n - 1];

	// Guaranteed miss (very unlikely collision)
	do {
		$needle_miss = randomString(16);
	} while (in_array($needle_miss, $a, true));

	$cases = [
		'start'  => $needle_start,
		'middle' => $needle_mid,
		'end'    => $needle_end,
		'miss'   => $needle_miss,
	];

	$s = $a;
	sort($s);
	$blockIndex = buildBlockIndex($s);
	$functions = getFunctionsByPrefix('search_');

	foreach ($cases as $case => $needle) {
		echo "\n--- Case: {$case} ---\n";

		$results = [];

		foreach ($functions as $fn) {
			$results[] = benchmark(
				function () use ($fn, $a, $s, $blockIndex, $needle) {
					if (str_contains($fn, 'binary')) {
						$fn($s, $needle);
					} elseif (str_contains($fn, 'block_index')) {
						$fn($s, $blockIndex, $needle);
					} else {
						$fn($a, $needle);
					}
				},
				$fn . '()',
				$iterations,
				$warmup,
			);
		}

		usort($results, fn($a, $b) => $a['time'] <=> $b['time']);

		echo str_repeat('-', 80) . "\n";
		printf(
			"%-32s | %-13s | %-13s | %-14s\n",
			'Method',
			'Median Time',
			'Avg Time',
			'Median Memory'
		);
		echo str_repeat('-', 80) . "\n";

		foreach ($results as $r) {
			printf(
				"%-32s | %10.3f µs | %10.3f µs | %14s\n",
				$r['label'],
				$r['time'] / 1e3,
				$r['time_avg'] / 1e3,
				formatBytes($r['mem'])
			);
		}

		echo str_repeat('-', 80) . "\n";
	}
}

foreach ([20, 200, 2000, 20000, 200000] as $n) {
	runSearchBenchmarks($n);
}
