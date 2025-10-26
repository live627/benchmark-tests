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
 * Generator yielding $piece $n times lazily.
 */
function stringGenerator(int $n, string $piece): Generator {
	for ($i = 0; $i < $n; $i++) {
		yield $piece;
	}
}

function runBenchmarks(int $n, string $piece = 'x'): void {
	echo "\n=== String Generation Benchmark for $n pieces ('{$piece}') ===\n";

	$results = [];

	// --- Different string generation methods ---
	$results[] = benchmark(function() use ($n, $piece) {
		$s = '';
		for ($i = 0; $i < $n; $i++) $s .= $piece;
	}, 'concatenation (.=)');

	$results[] = benchmark(function() use ($n, $piece) {
		$parts = [];
		for ($i = 0; $i < $n; $i++) $parts[] = $piece;
		$s = implode('', $parts);
	}, 'implode(array)');

	$results[] = benchmark(function() use ($n, $piece) {
		$s = str_repeat($piece, $n);
	}, 'str_repeat()');

	$results[] = benchmark(function() use ($n, $piece) {
		$s = '';
		for ($i = 0; $i < $n; $i++) {
			$s .= sprintf('%s', $piece);
		}
	}, 'sprintf() in loop');

	$results[] = benchmark(function() use ($n, $piece) {
		$s = '';
		foreach (stringGenerator($n, $piece) as $i) {
			$s .= $i;
		}
	}, 'generator loop');

	$results[] = benchmark(function() use ($n, $piece) {
		$s = implode('', iterator_to_array(stringGenerator($n, $piece), false));
	}, 'generator+implode');

	$results[] = benchmark(function() use ($n, $piece) {
		$s = '';
		for ($i = 0; $i < $n; $i++) {
			$s = "{$s}{$piece}";
		}
	}, 'interpolation loop');

	$results[] = benchmark(function() use ($n, $piece) {
		// Using pack() to generate n identical bytes
		// For printable ASCII use "C*" and unpack characters as ord() values of $piece
		$byte = ord($piece);
		$s = pack('C' . $n, ...array_fill(0, $n, $byte));
	}, 'pack("C$n")');

	$results[] = benchmark(function() use ($n, $piece) {
		// Using pack() to generate n identical bytes
		// For printable ASCII use "C*" and unpack characters as ord() values of $piece
		$byte = ord($piece);
		$s = pack('C*', ...array_fill(0, $n, $byte));
	}, 'pack("C*")');

	$results[] = benchmark(function() use ($n, $piece) {
		$s = pack('A', str_repeat($piece, $n));
	}, 'pack("A")');

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
	runBenchmarks($n, 'A');
}
