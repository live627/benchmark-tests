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
	memory_reset_peak_usage();
	$start = hrtime(true);
	$fn();
	$end = hrtime(true);
	return [
		'label' => $label,
		'time_ms' => ($end - $start) / 1e6,
		'mem_used' => memory_get_peak_usage(),
	];
}

/**
 * Generator that yields array chunks lazily (no full copy).
 */
function chunkGenerator(array $arr, int $chunkSize): Generator {
	$len = count($arr);
	for ($i = 0; $i < $len; $i += $chunkSize) {
		yield array_slice($arr, $i, $chunkSize);
	}
}

function sliceGenerator(array $arr, int $offset, int $length): iterable {
	$end = $offset + $length;
	for ($i = $offset; $i < $end && $i < count($arr); $i++) {
		yield $arr[$i];
	}
}

function runBenchmarks(int $n, int $chunkSize = 100): void {
	echo "\n=== Benchmark for $n elements (chunk size $chunkSize) ===\n";

	$arr = range(1, $n);
	$results = [];

	// array_slice loop
	$results[] = benchmark(function() use ($arr, $chunkSize) {
		$chunks = [];
		$len = count($arr);
		for ($i = 0; $i < $len; $i += $chunkSize) {
			$chunks[] = array_slice($arr, $i, $chunkSize);
		}
		foreach ($chunks as $chunk) {
			// simulate work on each chunk
			$x = $chunk[0];
		}
	}, 'array_slice loop');

	// array_chunk
	$results[] = benchmark(function() use ($arr, $chunkSize) {
		$chunks = array_chunk($arr, $chunkSize);
		foreach ($chunks as $chunk) {
			// simulate work on each chunk
			$x = $chunk[0];
		}
	}, 'array_chunk');

	// manual iteration (no copies)
	$results[] = benchmark(function() use ($arr, $chunkSize) {
		$len = count($arr);
		for ($i = 0; $i < $len; $i += $chunkSize) {
			for ($j = $i; $j < $i + $chunkSize && $j < $len; $j++) {
				$x = $arr[$j]; // simulate work
			}
		}
	}, 'manual iteration');

	// generator chunker (lazy)
	$results[] = benchmark(function() use ($arr, $chunkSize) {
		foreach (chunkGenerator($arr, $chunkSize) as $chunk) {
			// simulate work on each chunk
			$x = $chunk[0];
		}
	}, 'generator chunks');

	// generator chunker (lazy)
	$results[] = benchmark(function() use ($arr, $chunkSize) {
		foreach (sliceGenerator($arr, 0, $chunkSize) as $chunk) {
			// simulate work on each chunk
			$x = $chunk;
		}
	}, 'generator chunks');

	// generator chunker (lazy)
	$results[] = benchmark(function() use ($arr, $chunkSize) {
		foreach (new LimitIterator(new ArrayIterator($arr), 0, $chunkSize) as $chunk) {
			// simulate work on each chunk
			$x = $chunk;
		}
	}, 'generator chunks');

	// print results
	foreach ($results as $r) {
		echo sprintf(
			"%-20s : %8.3f ms | %9s memory\n",
			$r['label'],
			$r['time_ms'],
			formatBytes($r['mem_used'])
		);
	}
}

// Run for multiple array sizes
foreach ([20000, 200000, 1000000] as $n) {
	runBenchmarks($n);
}
