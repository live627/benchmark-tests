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
 * Generator that yields substr chunks lazily (no full copy array).
 */
function chunkGenerator(string $str, int $chunkSize): Generator {
	$len = strlen($str);
	for ($i = 0; $i < $len; $i += $chunkSize) {
		yield substr($str, $i, $chunkSize);
	}
}

/**
 * Slice generator version (start/length)
 */
function sliceGenerator(string $str, int $offset, int $length): iterable {
	$end = $offset + $length;
	$len = strlen($str);
	for ($i = $offset; $i < $end && $i < $len; $i++) {
		yield $str[$i];
	}
}

function runBenchmarks(int $n, int $chunkSize = 100): void {
	echo "\n=== Benchmark for $n chars (chunk size $chunkSize) ===\n";

	$str = str_repeat('A', $n);
	$results = [];

	// substr loop
	$results[] = benchmark(function() use ($str, $chunkSize) {
		$chunks = [];
		$len = strlen($str);
		for ($i = 0; $i < $len; $i += $chunkSize) {
			$chunks[] = substr($str, $i, $chunkSize);
		}
		foreach ($chunks as $chunk) {
			$x = $chunk[0]; // simulate work
		}
	}, 'substr loop');

	// str_split
	$results[] = benchmark(function() use ($str, $chunkSize) {
		$chunks = str_split($str, $chunkSize);
		foreach ($chunks as $chunk) {
			$x = $chunk[0]; // simulate work
		}
	}, 'str_split');

	// manual iteration (no substr copies)
	$results[] = benchmark(function() use ($str, $chunkSize) {
		$len = strlen($str);
		for ($i = 0; $i < $len; $i += $chunkSize) {
			for ($j = $i; $j < $i + $chunkSize && $j < $len; $j++) {
				$x = $str[$j]; // simulate work
			}
		}
	}, 'manual iteration');

	// generator chunks (lazy substr)
	$results[] = benchmark(function() use ($str, $chunkSize) {
		foreach (chunkGenerator($str, $chunkSize) as $chunk) {
			$x = $chunk[0];
		}
	}, 'generator chunks');

	// generator slices (char-wise)
	$results[] = benchmark(function() use ($str, $chunkSize) {
		foreach (sliceGenerator($str, 0, $chunkSize) as $ch) {
			$x = $ch;
		}
	}, 'generator slices');

	// SplFileObject-like iteration via SplFixedArray simulation
	$results[] = benchmark(function() use ($str, $chunkSize) {
		$len = strlen($str);
		$it = new LimitIterator(new ArrayIterator(str_split($str)), 0, $chunkSize);
		foreach ($it as $ch) {
			$x = $ch;
		}
	}, 'iterator limit');

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

// Run for multiple string sizes
foreach ([20000, 200000, 1000000] as $n) {
	runBenchmarks($n);
}
