<?php

declare(strict_types=1);

use SMF\Uuid;
use SMF\MyUuid;

require_once __DIR__ . '/Uuid.php';
require_once __DIR__ . '/MyUuid.php';

function fake_random_bytes(int $length): string
{
	static $state = 123456789;
	$out = '';

	$i = 0;
	while ($i < $length) {
		$state = (1664525 * $state + 1013904223) & 0xffffffff;

		$out .= chr(($state >> 16) & 0xff);
		$i++;
	}

	return $out;
}

const ITERATIONS = 10000;
const SAMPLES = 15;
const WARMUP_ITERATIONS = 5000;

/**
 * Benchmark a callable.
 */
function benchmark(
	string $label,
	callable $fn,
	int $iterations = ITERATIONS,
	int $samples = SAMPLES,
): array {
	/******************************************************************************
	 * Warmup
	 *****************************************************************************/

	for ($i = 0; $i < WARMUP_ITERATIONS; $i++) {
		$fn($i);
	}

	/******************************************************************************
	 * Reduce GC variance during timing
	 *****************************************************************************/

	$gcEnabled = gc_enabled();

	gc_collect_cycles();
	gc_disable();

	$times = [];

	try {
		for ($sample = 0; $sample < $samples; $sample++) {
			$result = null;

			$start = hrtime(true);

			for ($i = 0; $i < $iterations; $i++) {
				$result = $fn($i);
			}

			$times[] = hrtime(true) - $start;

			// Prevent optimizer weirdness.
			if ($result === '__never__') {
				echo '';
			}
		}
	} finally {
		if ($gcEnabled) {
			gc_enable();
		}
	}

	sort($times);

	// Drop lowest/highest 2 samples.
	$times = array_slice($times, 2, -2);

	$median = $times[intdiv(count($times), 2)];
	$min = $times[0];
	$max = array_last($times);
	$average = array_sum($times) / $samples;

	return [
		'label' => $label,

		'time_ms' => $median / 1_000_000,
		'best_ms' => $min / 1_000_000,
		'worst_ms' => $max / 1_000_000,
		'avg_ms' => $average / 1_000_000,

		'ns_per_op' => $median / $iterations,
		'ops_per_sec' => $iterations / ($median / 1_000_000_000),

		'spread_ms' => ($max - $min) / 1_000_000,
	];
}

/**
 * Print benchmark row.
 */
function printResult(array $r): void
{
	printf(
		"%-32s %10.2f ms %10.2f ns/op %12.0f ops/s %10.2f best %10.2f worst %10.2f avg %10.2f spread\n",
		$r['label'],
		$r['time_ms'],
		$r['ns_per_op'],
		$r['ops_per_sec'],
		$r['best_ms'],
		$r['worst_ms'],
		$r['avg_ms'],
		$r['spread_ms'],
	);
}

/**
 * Verify equality.
 */
function assertEqual(
	mixed $expected,
	mixed $actual,
	string $message
): void {
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message .
			PHP_EOL .
			'Expected: ' . var_export($expected, true) .
			PHP_EOL .
			'Actual:   ' . var_export($actual, true)
		);
	}
}

/**
 * Verify UUID structure.
 */
function assertValidUuid(string $uuid): void
{
	if (
		!preg_match(
			'~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~',
			$uuid
		)
	) {
		throw new RuntimeException("Invalid UUID: {$uuid}");
	}
}

/******************************************************************************
 * Correctness checks
 *****************************************************************************/

echo PHP_EOL;
echo "Running correctness checks..." . PHP_EOL;

$classes = [
	Uuid::class,
	MyUuid::class,
];

foreach ($classes as $class) {
	echo "Testing {$class}" . PHP_EOL;

	/******************************************************************************
	 * v4 generation
	 *****************************************************************************/

	$v4 = (string) new $class(4);

	assertValidUuid($v4);

	/******************************************************************************
	 * v7 generation
	 *****************************************************************************/

	$v7 = (string) new $class(7);

	assertValidUuid($v7);

	/******************************************************************************
	 * binary roundtrip
	 *****************************************************************************/

	$binary = $class::compress($v7, $class::COMPRESS_BINARY);

	assertEqual(
		16,
		strlen($binary),
		'Binary UUID must be 16 bytes'
	);

	assertEqual(
		strtolower($v7),
		strtolower($class::expand($binary)),
		'Binary roundtrip failed'
	);

	/******************************************************************************
	 * base64 roundtrip
	 *****************************************************************************/

	$b64 = $class::compress($v7, $class::COMPRESS_BASE64);

	assertEqual(
		22,
		strlen($b64),
		'Base64 UUID must be 22 chars'
	);

	assertEqual(
		strtolower($v7),
		strtolower($class::expand($b64)),
		'Base64 roundtrip failed'
	);

	/******************************************************************************
	 * base32 roundtrip
	 *****************************************************************************/

	$b32 = $class::compress($v7, $class::COMPRESS_BASE32);

	assertEqual(
		26,
		strlen($b32),
		'Base32 UUID must be 26 chars'
	);

	assertEqual(
		strtolower($v7),
		strtolower($class::expand($b32)),
		'Base32 roundtrip failed'
	);

	/******************************************************************************
	 * parse canonical UUID
	 *****************************************************************************/

	$parsed = $class::createFromString($v7);

	assertEqual(
		strtolower($v7),
		strtolower((string) $parsed),
		'Canonical parse failed'
	);

	/******************************************************************************
	 * binary conversion consistency
	 *****************************************************************************/

	assertEqual(
		$binary,
		$parsed->getBinary(),
		'Binary conversion mismatch'
	);

	echo "  OK" . PHP_EOL;
}

echo PHP_EOL;
echo "Correctness checks passed." . PHP_EOL;

/******************************************************************************
 * Warmup
 *****************************************************************************/

echo PHP_EOL;
echo "Warming up..." . PHP_EOL;

for ($i = 0; $i < 10000; $i++) {
	new Uuid(7);
	new MyUuid(7);
}

echo "Warmup complete." . PHP_EOL;

/******************************************************************************
 * Shared samples
 *****************************************************************************/

$uuidV4 = (string) new Uuid(4);
$uuidV7 = (string) new Uuid(7);

$uuidV4Binary = Uuid::compress($uuidV4, Uuid::COMPRESS_BINARY);
$uuidV7Binary = Uuid::compress($uuidV7, Uuid::COMPRESS_BINARY);

$uuidV7B64 = Uuid::compress($uuidV7, Uuid::COMPRESS_BASE64);
$uuidV7B32 = Uuid::compress($uuidV7, Uuid::COMPRESS_BASE32);

/******************************************************************************
 * Benchmarks
 *****************************************************************************/

echo PHP_EOL;
echo str_repeat('=', 140) . PHP_EOL;

printf(
	"%-32s %10s %12s %12s %10s %10s %10s %10s\n",
	'Benchmark',
	'time',
	'ns/op',
	'ops/sec',
	'best',
	'worst',
	'avg',
	'spread'
);

echo str_repeat('-', 140) . PHP_EOL;

/******************************************************************************
 * Shared reusable objects
 *****************************************************************************/

$uuidObj = new Uuid(7);
$myUuidObj = new MyUuid(7);

$parsedUuid = Uuid::createFromString($uuidV7);
$parsedMyUuid = MyUuid::createFromString($uuidV7);

$benchmarks = [

	/******************************************************************************
	 * UUID v4 generation
	 *****************************************************************************/

	[
		'Uuid v4 generation',
		fn() => new Uuid(4),
	],

	[
		'MyUuid v4 generation',
		fn() => new MyUuid(4),
	],

	/******************************************************************************
	 * UUID v7 generation
	 *****************************************************************************/

	[
		'Uuid v7 generation',
		fn() => new Uuid(7),
	],

	[
		'MyUuid v7 generation',
		fn() => new MyUuid(7),
	],

	/******************************************************************************
	 * Parse canonical UUID
	 *****************************************************************************/

	[
		'Uuid parse canonical',
		fn() => Uuid::createFromString($uuidV7),
	],

	[
		'MyUuid parse canonical',
		fn() => MyUuid::createFromString($uuidV7),
	],

	/******************************************************************************
	 * Parse binary UUID
	 *****************************************************************************/

	[
		'Uuid parse binary',
		fn() => Uuid::createFromString($uuidV7Binary),
	],

	[
		'MyUuid parse binary',
		fn() => MyUuid::createFromString($uuidV7Binary),
	],

	/******************************************************************************
	 * Base64 compression
	 *****************************************************************************/

	[
		'Uuid compress base64',
		fn() => Uuid::compress($uuidV7, Uuid::COMPRESS_BASE64),
	],

	[
		'MyUuid compress base64',
		fn() => MyUuid::compress($uuidV7, MyUuid::COMPRESS_BASE64),
	],

	/******************************************************************************
	 * Base32 compression
	 *****************************************************************************/

	[
		'Uuid compress base32',
		fn() => Uuid::compress($uuidV7, Uuid::COMPRESS_BASE32),
	],

	[
		'MyUuid compress base32',
		fn() => MyUuid::compress($uuidV7, MyUuid::COMPRESS_BASE32),
	],

	/******************************************************************************
	 * String conversion
	 *****************************************************************************/

	[
		'Uuid string conversion',
		fn() => (string) $uuidObj,
	],
	[
		'MyUuid string conversion',
		fn() => (string) $myUuidObj,
	],

	/******************************************************************************
	 * Binary conversion
	 *****************************************************************************/

	[
		'Uuid binary conversion',
		fn() => $uuidObj->getBinary(),
	],
	 [
		'MyUuid binary conversion',
		fn() => $myUuidObj->getBinary(),
	],
];

foreach ($benchmarks as [$label, $fn]) {
	printResult(
		benchmark($label, $fn)
	);
}

echo str_repeat('=', 140) . PHP_EOL;
echo PHP_EOL;

