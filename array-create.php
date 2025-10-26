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
	$startMem = memory_get_peak_usage();
	$start = hrtime(true);
	$fn();
	$end = hrtime(true);
	$memUsed = memory_get_peak_usage() - $startMem;
	return [
		'label' => $label,
		'time_ms' => ($end - $start) / 1e6,
		'mem_used' => $memUsed,
	];
}

/**
 * Simple generator that yields integers lazily.
 */
function rangeGenerator(int $n): Generator {
	for ($i = 1; $i <= $n; $i++) {
		yield $i;
	}
}

function runBenchmarks(int $n): void {
	echo "\n=== Array Generation Benchmark for $n elements ===\n";

	$results = [];

	// --- Generation methods ---
	$results[] = benchmark(fn() => $a = range(1, $n), 'range()');
	$results[] = benchmark(fn() => $a = array_fill(0, $n, 0), 'array_fill()');
	$results[] = benchmark(function() use ($n) {
		$a = [];
		for ($i = 1; $i <= $n; $i++) $a[] = $i;
	}, 'for loop $a[]=');
	$results[] = benchmark(function() use ($n) {
		$a = [];
		for ($i = 1; $i <= $n; $i++) array_push($a, $i);
	}, 'array_push() loop');
	$results[] = benchmark(function() use ($n) {
		$a = iterator_to_array(rangeGenerator($n), false);
	}, 'generator');
	$results[] = benchmark(function() use ($n) {
		$a = new SplFixedArray($n);
		for ($i = 0; $i < $n; $i++) $a[$i] = $i + 1;
	}, 'SplFixedArray');

	// --- Print Table ---
	echo str_repeat('-', 60) . "\n";
	printf("%-25s | %-14s | %-15s\n", 'Method', 'Time (ms)', 'Memory');
	echo str_repeat('-', 60) . "\n";

	foreach ($results as $r) {
		printf("%-25s | %11.3f ms | %15s\n",
			$r['label'],
			$r['time_ms'],
			formatBytes($r['mem_used'])
		);
	}
	echo str_repeat('-', 60) . "\n";
}

// Run for various sizes
foreach ([20000, 200000, 1000000] as $n) {
	runBenchmarks($n);
}
