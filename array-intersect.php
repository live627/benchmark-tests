<?php

// Source - https://stackoverflow.com/a/9276284
// Posted by kingmaple, modified by community. See post 'Timeline' for change history
// Retrieved 2026-04-08, License - CC BY-SA 4.0

// Source - https://stackoverflow.com/a/53203232
// Posted by slaszu, modified by community. See post 'Timeline' for change history
// Retrieved 2026-04-08, License - CC BY-SA 4.0

ini_set('memory_limit', '2048M');

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
	$mem = -memory_get_peak_usage();
	$time = -hrtime(true);
	$fn();
	$time += hrtime(true);
	$mem += memory_get_peak_usage();
	return [
		'label' => $label,
		'time_ms' => $time / 1e6,
		'mem_used' => $mem,
	];
}

function manual_intersect($arrayOne, $arrayTwo) {
	$index = array_flip($arrayOne);
	foreach ($arrayTwo as $value) {
		if (isset($index[$value])) {
			unset($index[$value]);
		}
	}
	foreach ($index as $value => $key) {
		unset($arrayOne[$key]);
	}
	return $arrayOne;
}

function flipped_intersect($arrayOne, $arrayTwo) {
	$index = array_flip($arrayOne);
	$second = array_flip($arrayTwo);
	$x = array_intersect_key($index, $second);
	return array_flip($x);
}


function runBenchmarks(int $n): void {
	echo "\n=== Array Intersection Benchmark for " . number_format($n) . " elements ===\n";

	// Generate test arrays
	$one = [];
	$two = [];
	for ($i = 0; $i < $n; $i++) {
		$one[] = rand(0, 1000000);
		$two[] = rand(0, 100000);
		$two[] = rand(0, 10000);
	}

	$one = array_unique($one);
	$two = array_unique($two);

	$results = [];

	$results[] = benchmark(
		fn() => $res = manual_intersect($one, $two),
		'manual_intersect()'
	);

	$results[] = benchmark(
		fn() => $res = array_intersect($one, $two),
		'array_intersect()'
	);

	$results[] = benchmark(
		fn() => $res = flipped_intersect($one, $two),
		'flipped_intersect()'
	);

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
foreach ([20, 20000, 200000, 1000000] as $n) {
	runBenchmarks($n);
}
