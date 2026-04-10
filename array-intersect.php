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
		'time' => $time,
		'mem' => $mem,
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

function reduce_intersect($arrayOne, $arrayTwo) {
	$second = array_flip($arrayTwo);
	return array_reduce($arrayOne, fn($c, $v) => isset($second[$v]) ? [...$c, $v] : $c, []);
}

function filter_flip_intersect($arrayOne, $arrayTwo) {
	$flipped = array_flip($arrayTwo);
	return array_filter($arrayOne, fn($v) => isset($flipped[$v]));
}

function diff_based_intersect($arrayOne, $arrayTwo) {
	return array_diff($arrayOne, array_diff($arrayOne, $arrayTwo));
}

function two_pointer_intersect($arrayOne, $arrayTwo) {
	sort($arrayOne);
	sort($arrayTwo);
	$result = [];
	$i = $j = 0;
	while ($i < count($arrayOne) && $j < count($arrayTwo)) {
		if ($arrayOne[$i] === $arrayTwo[$j]) {
			$result[] = $arrayOne[$i];
			$i++; $j++;
		} elseif ($arrayOne[$i] < $arrayTwo[$j]) {
			$i++;
		} else {
			$j++;
		}
	}
	return $result;
}

function hash_set_intersect($arrayOne, $arrayTwo) {
	$hashSet = array_combine($arrayTwo, $arrayTwo);
	return array_filter($arrayOne, fn($v) => isset($hashSet[$v]));
}

function hash_set_intersect2(array $arrayOne, array $arrayTwo): array
{
	$hashSet = array_combine($arrayTwo, $arrayTwo);
	$result = [];

	foreach ($arrayOne as $value) {
		if (isset($hashSet[$value])) {
			$result[] = $value;
		}
	}

	return $result;
}

function hash_set_intersect3(array $arrayOne, array $arrayTwo): array
{
	$hashSet = array_flip($arrayTwo);
	$result = [];

	foreach ($arrayOne as $value) {
		if (isset($hashSet[$value])) {
			$result[] = $value;
		}
	}

	return $result;
}

function hash_set_intersect4(array $arrayOne, array $arrayTwo): array
{
	$hashSet = array_flip($arrayTwo);

	foreach ($arrayOne as $key => $value) {
		if (!isset($hashSet[$value])) {
			unset($arrayOne[$key]);
		}
	}

	return $arrayOne;
}

function normalizeResult(array $arr): array {
	sort($arr);                // ignore order
	$arr = array_values($arr); // ignore keys
	return $arr;
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

	$functions = [
		'manual_intersect',
		'array_intersect',
		'flipped_intersect',
		'reduce_intersect',
		'filter_flip_intersect',
		'diff_based_intersect',
		'two_pointer_intersect',
		'hash_set_intersect',
		'hash_set_intersect2',
		'hash_set_intersect3',
		'hash_set_intersect4',
	];

	$results = [];
	$outputs = [];

	foreach ($functions as $fn) {
		$results[] = benchmark(
			function () use ($fn, $one, $two, &$outputs) {
				$outputs[$fn] = $fn($one, $two);
			},
			$fn . '()'
		);
	}
 
	// --- Print Table ---
	echo str_repeat('-', 60) . "\n";
	printf("%-25s | %-14s | %-15s\n", 'Method', 'Time (ms)', 'Memory');
	echo str_repeat('-', 60) . "\n";

	usort($results, function ($a, $b) {
		$scoreA = $a['time'] * 0.7 + $a['mem'] * 0.3;
		$scoreB = $b['time'] * 0.7 + $b['mem'] * 0.3;

		return $scoreA <=> $scoreB;
	});

	foreach ($results as $r) {
		printf("%-25s | %11.3f ms | %15s\n",
			$r['label'],
			$r['time'] / 1e6,
			formatBytes($r['mem'])
		);
	}
	echo str_repeat('-', 60) . "\n";

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
foreach ([20, 20000, 200000, 1000000] as $n) {
	runBenchmarks($n);
}
