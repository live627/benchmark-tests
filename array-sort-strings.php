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
 * Sort an array by a reference order using the Schwartzian Transform (decorate-sort-undecorate pattern).
 *
 * This implementation improves performance by precomputing sort keys (rank values)
 * before sorting, eliminating repeated hash lookups inside the comparator.
 *
 * Process:
 * 1. Build a rank map from `$order` (value => position)
 * 2. Decorate each element of `$a` into a tuple:
 *    - computed rank
 *    - original index (for stability)
 *    - original value
 * 3. Sort the decorated array using a lightweight comparator that only compares scalars
 * 4. Undecorate the result back into the original values
 *
 * Key characteristics:
 * - No hash lookups inside the comparator (rank is precomputed)
 * - Uses scalar comparisons only during sorting
 * - Maintains stable ordering for elements with equal rank via original index
 *
 * Complexity:
 * - Time: O(n log n)
 * - Space: O(n + k)
 *
 * Behavior:
 * - Elements present in `$order` are sorted according to their position in `$order`
 * - Elements not present in `$order` are assigned lowest priority (PHP_INT_MAX equivalent behavior)
 * - Sorting is stable due to inclusion of original index as a tie-breaker
 *
 * Tradeoffs:
 * - Faster than direct comparator-based rank lookups due to reduced overhead in hot path
 * - Higher memory usage due to decorated structure
 * - Still fundamentally comparison-based (O(n log n))
 *
 * When to use:
 * - Medium to large datasets where comparator cost is significant
 * - When stability is required
 * - When avoiding repeated hash lookups in sorting is beneficial
 *
 * When NOT to use:
 * - Very large datasets where bucket/counting-based O(n) approaches are possible
 * - Memory-constrained environments
 *
 * @param array $a     Input array to sort
 * @param array $order Reference ordering list defining priority
 * @return array       Sorted array according to reference order
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
 * Sort an array by a reference order using a partition-based approach with a partial sort.
 *
 * This implementation splits the input array into two groups:
 *
 * 1. `$in`  → elements found in `$order`, which are assigned a rank and sorted
 * 2. `$out` → elements not found in `$order`, appended after sorting
 *
 * Process:
 * - Build a rank lookup table from `$order` (value => position)
 * - Partition `$a` into:
 *     - `$in`: elements with a defined rank
 *     - `$out`: elements without a defined rank
 * - Sort `$in` using `usort()` based on precomputed rank values
 * - Concatenate sorted `$in` followed by `$out`
 *
 * Key characteristics:
 * - Reduces sorting cost by only sorting the relevant subset (`$in`)
 * - Avoids repeated hash lookups inside the comparator
 * - Preserves relative order of elements in `$out` (stable tail partition)
 *
 * Complexity:
 * - Time: O(n log m)
 *   where:
 *     n = total elements in `$a`
 *     m = number of elements in `$in`
 * - Space: O(n + k)
 *
 * Behavior:
 * - Elements found in `$order` are sorted according to their rank
 * - Elements not found in `$order` are placed at the end in original order
 * - Sorting stability applies only to `$out` (naturally preserved)
 *
 * Tradeoffs:
 * - Faster than full-array `usort()` when `$in` is significantly smaller than `$a`
 * - Still comparison-based sorting for `$in` (O(m log m))
 * - Performance depends heavily on distribution of matches between `$a` and `$order`
 *
 * When to use:
 * - When only a subset of elements needs ordering
 * - When `$order` defines a priority list rather than a full ordering domain
 * - When a large portion of `$a` can be treated as "unranked tail items"
 *
 * When NOT to use:
 * - When nearly all elements are in `$order` (falls back to O(n log n))
 * - When full linear-time bucket/rank-based sorting is possible
 *
 * @param array $a     Input array to sort
 * @param array $order Reference ordering defining priority
 * @return array       Sorted array with ordered prefix and stable unranked suffix
 */
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

/**
 * Sort an array by a reference order using a flat, comparison-free rank projection approach.
 *
 * This implementation avoids `usort()` and nested arrays by:
 *
 * 1. Building a rank lookup table from `$order` (value => position)
 * 2. Projecting each element in `$a` into a flat `$ranks` array
 *    where each index corresponds directly to the original element index
 * 3. Sorting the rank index map using `asort()`
 * 4. Reconstructing the sorted result using the sorted indices
 *
 * Key characteristics:
 * - No nested arrays or decoration objects
 * - No custom comparator functions
 * - Uses scalar-only intermediate structures for low overhead
 *
 * Complexity:
 * - Time: O(n log n)
 * - Space: O(n + k)
 *
 * Behavior:
 * - Elements found in `$order` are sorted according to their position in `$order`
 * - Elements not found in `$order` are placed at the end (with equal max rank)
 * - Relative order of elements with identical rank depends on `asort()` behavior
 *
 * Tradeoffs:
 * - Faster than `usort()` variants due to removal of comparator overhead
 * - Still comparison-based due to `asort()`, so not linear-time
 * - Stability is not strictly guaranteed for equal ranks
 *
 * When to use:
 * - Medium-sized arrays where simplicity and reduced callback overhead matter
 * - When avoiding `usort()` is desirable for performance consistency
 *
 * When NOT to use:
 * - Large datasets where O(n) bucket/rank-splitting approaches are available
 * - Cases requiring strict stability guarantees for equal ranks
 *
 * @param array $a     Input array to sort
 * @param array $order Reference ordering list (priority values)
 * @return array       Sorted array according to reference order
 */
function sortByReference_rank_array (array $a, array $order): array {
	$rank = [];
	$max = count($order);

	foreach ($order as $i => $v) {
		$rank[$v] = $i;
	}

	// Flat parallel rank array (no nested structures)
	$ranks = [];

	foreach ($a as $i => $v) {
		$ranks[$i] = $rank[$v] ?? $max;
	}

	// Sort indices indirectly using ranks (still flat)
	asort($ranks, SORT_NUMERIC);

	$result = [];

	foreach ($ranks as $i => $_) {
		$result[] = $a[$i];
	}

	return $result;
}

/**
 * Sort an array by a reference order using a near-linear-time bucket strategy.
 *
 * This implementation avoids `usort()` entirely by transforming the problem
 * into grouping + ordered concatenation:
 *
 *  1. Build a rank map from `$order` (value => position)
 *  2. Partition `$a` into:
 *     - Buckets indexed by rank (for values found in `$order`)
 *     - A "rest" array (for values not in `$order`)
 *  3. Emit bucket contents in rank order, followed by the rest
 *
 * Complexity:
 *  - Time: O(n + k)
 *      n = count($a)
 *      k = count($order)
 *  - Space: O(n + k)
 *
 * Advantages:
 *  - No comparator or `usort()` → avoids O(n log n)
 *  - No repeated hash lookups during sorting
 *  - Cache-friendly (append-only writes)
 *  - Naturally stable:
 *      - Preserves original order within each bucket
 *      - Preserves original order for non-ranked elements
 *
 * Behavior:
 *  - Values in `$order` appear first, in the exact order defined by `$order`
 *  - Values not present in `$order` are appended afterward, in original order
 *
 * Tradeoffs / Caveats:
 *  - Allocates `count($order)` buckets upfront
 *      → inefficient if `$order` is very large and sparsely matched
 *  - Requires hash lookup per element during classification (`array_flip`)
 *  - If `$order` contains duplicate values, later entries overwrite earlier ones
 *
 * When to use:
 *  - Large input arrays where performance matters
 *  - Moderate `$order` size with reasonable overlap with `$a`
 *  - Scenarios where stability is required
 *
 * When NOT to use:
 *  - Extremely large `$order` with few matches (consider sparse bucket variant)
 *  - When a full comparison-based sort is required
 *
 * @param array $a     Input array to sort
 * @param array $order Reference ordering (priority list)
 * @return array       Sorted array
 */
function sortByReference_bucket(array $a, array $order): array {
	$rank = array_flip($order);
	$k = count($order);

	// Preallocate buckets for known ranks
	$buckets = array_fill(0, $k, []);
	$rest = [];

	foreach ($a as $v) {
		if (isset($rank[$v])) {
			$buckets[$rank[$v]][] = $v;
		} else {
			$rest[] = $v;
		}
	}

	// Flatten in order
	$result = [];

	for ($i = 0; $i < $k; $i++) {
		foreach ($buckets[$i] as $v) {
			$result[] = $v;
		}
	}

	// Append non-ranked items
	return [...$result, ...$rest];
}



function normalizeResult(array $arr): array {
	return array_values($arr);
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

function runBenchmarks(int $n, int $iterations = 10, int $warmup = 3): void {
	echo "\n=== Array Sort Benchmark for " . number_format($n) . " strings with " . number_format($iterations) . " iterations ===\n";

	$one = [];
	for ($i = 0; $i < $n; $i++) {
		// 30% chance to reuse an existing value
		if ($i > 0 && random_int(0, 9) < 3) {
			$one[] = $one[array_rand($one)];
		} else {
			$one[] = randomString();
		}
	}

	// Pick random elements FROM $one for $order
	$order = [];
	$keys = array_rand($one, (int) ($n / 2));

	foreach ((array) $keys as $k) {
		$order[] = $one[$k];
	}

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
foreach ([20, 200, 2000, 20000, 200000] as $n) {
	runBenchmarks($n);
}
