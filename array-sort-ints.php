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

/**
 * Build rank map from $order and sort using it (fastest typical approach)
 */
function sortByReference_hash(array $a, array $order): array {
	$rank = array_flip($order);

	usort($a, function ($x, $y) use ($rank) {
		$rx = $rank[$x] ?? PHP_INT_MAX;
		$ry = $rank[$y] ?? PHP_INT_MAX;

		return $rx <=> $ry;
	});

	return $a;
}

/**
 * Same idea but without precomputed map (slow)
 */
//~ function sortByReference_naive(array $a, array $order): array {
	//~ usort($a, function ($x, $y) use ($order) {
		//~ $rx = array_search($x, $order, true);
		//~ $ry = array_search($y, $order, true);

		//~ $rx = $rx === false ? PHP_INT_MAX : $rx;
		//~ $ry = $ry === false ? PHP_INT_MAX : $ry;

		//~ return $rx <=> $ry;
	//~ });

	//~ return $a;
//~ }

/**
 * Decorate → sort → undecorate (avoids repeated lookups)
 */
function sortByReference_schwartz(array $a, array $order): array {
	$rank = array_flip($order);

	$decorated = [];
	foreach ($a as $v) {
		$decorated[] = [
			'value' => $v,
			'rank'  => $rank[$v] ?? PHP_INT_MAX
		];
	}

	usort($decorated, fn($x, $y) => $x['rank'] <=> $y['rank']);

	return array_column($decorated, 'value');
}

/**
 * array_multisort variant
 */
function sortByReference_multisort(array $a, array $order): array {
	$rank = array_flip($order);

	$ranks = [];
	$positions = [];

	foreach ($a as $i => $v) {
		$ranks[$i] = $rank[$v] ?? PHP_INT_MAX;
		$positions[$i] = $i; // tie-breaker for stability
	}

	array_multisort(
		$ranks, SORT_ASC,
		$positions, SORT_ASC,
		$a
	);

	return $a;
}

/**
 * Stable bucket approach (VERY fast when domain is small)
 */
function sortByReference_bucket(array $a, array $order): array {
	$buckets = [];

	foreach ($a as $v) {
		$buckets[$v][] = $v;
	}

	$result = [];

	// first: values in $order
	foreach ($order as $v) {
		if (isset($buckets[$v])) {
			foreach ($buckets[$v] as $item) {
				$result[] = $item;
			}
			unset($buckets[$v]);
		}
	}

	// then: everything else
	foreach ($buckets as $group) {
		foreach ($group as $item) {
			$result[] = $item;
		}
	}

	return $result;
}

function sortByReference_asort(array $a, array $order): array {
	$rank = array_flip($order);
	$ranks = [];

	foreach ($a as $v) {
		$ranks[] = $rank[$v] ?? PHP_INT_MAX;
	}

	asort($ranks, SORT_NUMERIC);

	$result = [];
	foreach ($ranks as $k => $_) {
		$result[] = $a[$k];
	}

	return $result;
}

function sortByReference_counting(array $a, array $order): array {
	$rank = array_flip($order);

	$buckets = [];

	foreach ($a as $v) {
		$r = $rank[$v] ?? PHP_INT_MAX;
		$buckets[$r][] = $v;
	}

	ksort($buckets);

	$result = [];
	foreach ($buckets as $group) {
		foreach ($group as $v) {
			$result[] = $v;
		}
	}

	return $result;
}

function sortByReference_partition(array $a, array $order): array {
	$rank = array_flip($order);

	$in = [];
	$out = [];

	foreach ($a as $v) {
		if (isset($rank[$v])) {
			$in[] = $v;
		} else {
			$out[] = $v;
		}
	}

	usort($in, fn($x, $y) => $rank[$x] <=> $rank[$y]);

	return [...$in, ...$out];
}

function sortByReference_uasort(array $a, array $order): array {
	$rank = array_flip($order);

	uasort($a, function ($x, $y) use ($rank) {
		return ($rank[$x] ?? PHP_INT_MAX)
		     <=> ($rank[$y] ?? PHP_INT_MAX);
	});

	return $a;
}


 function sortByReference_hash_static(array $a, array $order): array {
	$rank = array_flip($order);

	usort($a, static function ($x, $y) use ($rank) {
		return ($rank[$x] ?? PHP_INT_MAX)
		     <=> ($rank[$y] ?? PHP_INT_MAX);
	});

	return $a;
}

function sortByReference_rank_array(array $a, array $order): array {
	$max = count($order);
	$rank = [];

	foreach ($order as $i => $v) {
		$rank[$v] = $i;
	}

	usort($a, fn($x, $y) =>
		($rank[$x] ?? $max) <=> ($rank[$y] ?? $max)
	);

	return $a;
}

function sortByReference_hybrid(array $a, array $order): array {
	$rank = array_flip($order);

	$buckets = [];
	$rest = [];

	foreach ($a as $v) {
		if (isset($rank[$v])) {
			$buckets[$rank[$v]][] = $v;
		} else {
			$rest[] = $v;
		}
	}

	ksort($buckets);

	$result = [];
	foreach ($buckets as $group) {
		foreach ($group as $v) {
			$result[] = $v;
		}
	}

	return [...$result, ...$rest];
}

function normalizeResult(array $arr): array {
	return array_values($arr);
}

function runBenchmarks(int $n, int $iterations = 10, int $warmup = 3): void {
	echo "\n=== Array Sort Benchmark for " . number_format($n) . " ints with " . number_format($iterations) . " iterations ===\n";

	$one = [];
	$order = [];

	for ($i = 0; $i < $n; $i++) {
		$one[] = rand(0, 100000);
	}

	for ($i = 0; $i < $n / 2; $i++) {
		$order[] = rand(0, 100000);
	}

	$one = array_values(array_unique($one));
	$order = array_values(array_unique($order));

	$functions = getFunctionsByPrefix('sortByReference_');

	$results = [];
	$outputs = [];

	foreach ($functions as $fn) {
		$results[] = benchmark(
			function () use ($fn, $one, $order, &$outputs) {
				$outputs[$fn] = $fn($one, $order);
			},
			$fn . '()',
			$iterations,
			$warmup,
		);
	}
 
	usort($results, function ($a, $b) {
		$scoreA = $a['time'] * 0.7 + $a['mem'] * 0.3;
		$scoreB = $b['time'] * 0.7 + $b['mem'] * 0.3;

		return $scoreA <=> $scoreB;
	});

	echo str_repeat('-', 95) . "\n";
	printf(
		"%-32s | %-13s | %-13s | %-14s\n",
		'Method',
		'Median Time',
		'Avg Time',
		'Median Memory'
	);
	echo str_repeat('-', 95) . "\n";

	foreach ($results as $r) {
		printf(
			"%-32s | %10.3f µs | %10.3f µs | %14s\n",
			$r['label'],
			$r['time'] / 1e3,
			$r['time_avg'] / 1e3,
			formatBytes($r['mem'])
		);
	}

	echo str_repeat('-', 95) . "\n";

	// --- Verify correctness ---
	$baselineFn = array_key_first($outputs);
	$baseline = normalizeResult($outputs[$baselineFn]);

	$allMatch = true;

	foreach ($outputs as $fn => $out) {
		if (normalizeResult($out) !== $baseline) {
			$allMatch = false;
			echo "❌ MISMATCH: {$fn} differs from {$baselineFn}\n";
		}
	}

	if ($allMatch) {
		echo "✅ All implementations match\n";
	}
}

// Run for various sizes
foreach ([20, 200, 2000, 20000] as $n) {
	runBenchmarks($n);
}
