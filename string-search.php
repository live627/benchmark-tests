<?php

/**
 * Format bytes as human-readable string.
 */
function format_bytes(int $bytes): string {
	$units = ['B', 'KB', 'MB', 'GB'];
	$i = 0;
	while ($bytes >= 1024 && $i < count($units) - 1) {
		$bytes /= 1024;
		$i++;
	}
	return sprintf("%.2f %s", $bytes, $units[$i]);
}

/**
 * Run and time a benchmark callable.
 */
function benchmark(callable $fn, string $label): array {
	gc_collect_cycles();
	gc_mem_caches();
	memory_reset_peak_usage();
	$start = hrtime(true);
	$result = $fn();
	$end = hrtime(true);
	return [
		'label' => $label,
		'time_ms' => ($end - $start) / 1e6,
		'mem_used' => memory_get_peak_usage(),
		'found' => $result ? 'yes' : 'no',
	];
}

/**
 * Generator that yields string chunks lazily.
 */
function chunk_generator(string $str, int $chunk_size): Generator {
	$len = strlen($str);
	for ($i = 0; $i < $len; $i += $chunk_size) {
		yield substr($str, $i, $chunk_size);
	}
}

/**
 * Generator that yields individual characters (or slices).
 */
function slice_generator(string $str, int $offset, int $length): Generator {
	$end = $offset + $length;
	$len = strlen($str);
	for ($i = $offset; $i < $end && $i < $len; $i++) {
		yield $str[$i];
	}
}

/**
 * Run benchmarks for a given string length and match position.
 */
function run(int $length, string $position): void {
	$padding1 = str_repeat('A', 88);
	$padding = str_repeat('A', $length);
	$needle = 'XYZ';

	switch ($position) {
		case 'beginning':
			$str = $padding1 . $needle . $padding;
			break;
		case 'middle':
			$mid = intdiv($length, 2);
			$str = substr($padding, 0, $mid) . $needle . substr($padding, $mid);
			break;
		case 'end':
			$str = $padding . $needle . $padding1;
			break;
		default:
			throw new InvalidArgumentException("Unknown position: $position");
	}

	echo "\n=== Searching {$length} chars, needle near {$position} ===\n";

	$results = [];

	// Built-in functions
	$results[] = benchmark(fn() => strpos($str, $needle) !== false, 'strpos');
	$results[] = benchmark(fn() => stripos($str, strtolower($needle)) !== false, 'stripos');
	$results[] = benchmark(fn() => strrpos($str, $needle) !== false, 'strrpos');
	$results[] = benchmark(fn() => str_contains($str, $needle), 'str_contains');

	// Regex-based
	$results[] = benchmark(fn() => preg_match('/XYZ/', $str), 'preg_match /XYZ/');
	$results[] = benchmark(fn() => preg_match('/xyz/i', $str), 'preg_match /xyz/i');
	$results[] = benchmark(fn() => preg_match('/' . preg_quote($needle, '/') . '/', $str), 'preg_match preg_quote');

	// Manual and generator-based
	$results[] = benchmark(function() use ($str, $needle) {
		$len = strlen($needle);
		$max = strlen($str) - $len;
		for ($i = 0; $i <= $max; $i++) {
			if (substr_compare($str, $needle, $i, $len) === 0) return true;
		}
		return false;
	}, 'substr_compare loop');

	$results[] = benchmark(function() use ($str, $needle) {
		$nlen = strlen($needle);
		$max = strlen($str) - $nlen;
		for ($i = 0; $i <= $max; $i++) {
			$match = true;
			for ($j = 0; $j < $nlen; $j++) {
				if ($str[$i + $j] !== $needle[$j]) {
					$match = false;
					break;
				}
			}
			if ($match) return true;
		}
		return false;
	}, 'manual loop');

	$results[] = benchmark(function() use ($str, $needle) {
		foreach (chunk_generator($str, 1024) as $chunk) {
			if (strpos($chunk, $needle) !== false) return true;
		}
		return false;
	}, 'chunk generator');

	$results[] = benchmark(function() use ($str, $needle) {
		$buffer = '';
		foreach (slice_generator($str, 0, strlen($str)) as $char) {
			$buffer .= $char;
			if (strlen($buffer) > 3) {
				$buffer = substr($buffer, -3); // keep last few chars
			}
			if (strpos($buffer, $needle) !== false) return true;
		}
		return false;
	}, 'slice generator');

	// Print results
	foreach ($results as $r) {
		echo sprintf(
			"%-25s : %8.3f ms | %8s | found: %s\n",
			$r['label'],
			$r['time_ms'],
			format_bytes($r['mem_used']),
			$r['found']
		);
	}
}

// Run for various string sizes and match positions
foreach ([10000, 100000, 1000000] as $n) {
	foreach (['beginning', 'middle', 'end'] as $pos) {
		run($n, $pos);
	}
}
