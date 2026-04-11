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

function manual_intersect($a, $b) {
	$index = array_flip($a);
	foreach ($b as $value) {
		if (isset($index[$value])) {
			unset($index[$value]);
		}
	}
	foreach ($index as $value => $key) {
		unset($a[$key]);
	}
	return $a;
}

function manual_intersect2($a, $b) {
	$index = array_flip($a);
	$res = [];
	foreach ($b as $value) {
		if (isset($index[$value])){
			$res[$value] = 1;
		}
	}
	return array_keys($res);
}

function flipped_intersect($a, $b) {
	$index = array_flip($a);
	$second = array_flip($b);
	$x = array_intersect_key($index, $second);
	return array_flip($x);
}

function reduce_intersect($a, $b) {
	$second = array_flip($b);
	return array_reduce($a, fn($c, $v) => isset($second[$v]) ? [...$c, $v] : $c, []);
}

function filter_flip_intersect($a, $b) {
	$flipped = array_flip($b);
	return array_filter($a, fn($v) => isset($flipped[$v]));
}

function diff_based_intersect($a, $b) {
	return array_diff($a, array_diff($a, $b));
}

function two_pointer_intersect($a, $b) {
	sort($a);
	sort($b);
	$result = [];
	$i = $j = 0;
	while ($i < count($a) && $j < count($b)) {
		if ($a[$i] === $b[$j]) {
			$result[] = $a[$i];
			$i++; $j++;
		} elseif ($a[$i] < $b[$j]) {
			$i++;
		} else {
			$j++;
		}
	}
	return $result;
}

function hash_set_intersect($a, $b) {
	$index = array_combine($b, $b);
	return array_filter($a, fn($v) => isset($index[$v]));
}

function hash_set_intersect2(array $a, array $b): array
{
	$index = array_combine($b, $b);
	$result = [];

	foreach ($a as $value) {
		if (isset($index[$value])) {
			$result[] = $value;
		}
	}

	return $result;
}

function hash_set_intersect3(array $a, array $b): array
{
	$index = array_flip($b);
	$result = [];

	foreach ($a as $value) {
		if (isset($index[$value])) {
			$result[] = $value;
		}
	}

	return $result;
}

function hash_set_intersect4(array $a, array $b): array
{
	$index = array_flip($b);

	foreach ($a as $key => $value) {
		if (!isset($index[$value])) {
			unset($a[$key]);
		}
	}

	return $a;
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
		'manual_intersect2',
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
