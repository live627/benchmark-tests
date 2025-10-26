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
 * Generator that yields each character of a string.
 */
function stringCharGenerator(string $s): Generator {
	$len = strlen($s);
	for ($i = 0; $i < $len; $i++) {
		yield $s[$i];
	}
}

function runBenchmarks(int $n): void {
	echo "\n=== String Iteration Benchmark for string length = $n ===\n";

	$str = str_repeat('A', $n);
	$results = [];

	$results[] = benchmark(function() use ($str) {
		for ($i = 0; $i < strlen($str); $i++) {
			$x = $str[$i];
		}
	}, 'for ($i) with strlen');

	$results[] = benchmark(function() use ($str) {
		$len = strlen($str);
		for ($i = 0; $i < $len; $i++) {
			$x = $str[$i];
		}
	}, 'for ($i) cached strlen');

	$results[] = benchmark(function() use ($str) {
		foreach (str_split($str) as $ch) {
			$x = $ch;
		}
	}, 'foreach str_split()');

	$results[] = benchmark(function() use ($str) {
		foreach (stringCharGenerator($str) as $ch) {
			$x = $ch;
		}
	}, 'foreach generator');

	$results[] = benchmark(function() use ($str) {
		foreach (unpack('C*', $str) as $ch) {
			$x = $ch;
		}
	}, 'foreach unpack("C*")');

	$results[] = benchmark(function() use ($str) {
		$len = strlen($str);
		$fixed = new SplFixedArray($len);
		for ($i = 0; $i < $len; $i++) {
			$fixed[$i] = $str[$i];
		}
		foreach ($fixed as $ch) {
			$x = $ch;
		}
	}, 'SplFixedArray iteration');

	// Print table
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
