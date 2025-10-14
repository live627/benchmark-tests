<?php

/**
 * SMF Query Cleaning Benchmark (Old vs Optimized)
 * Includes per-query average time (µs/query)
 */

// ------------------------------------------------------------
// OLD cleaner (slow baseline)
// ------------------------------------------------------------
function smf2_cleaner($db_string)
{
	// Comments that are allowed in a query are preg_removed.
	static $allowed_comments_from = array(
		'~\s+~s',
		'~/\*!40001 SQL_NO_CACHE \*/~',
		'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
		'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
	);
	static $allowed_comments_to = array(
		' ',
		'',
		'',
		'',
	);

	$clean = '';
	$old_pos = 0;
	$pos = -1;
	// Remove the string escape for better runtime
	$db_string_1 = str_replace('\\\'', '', $db_string);
	while (true)
	{
		$pos = strpos($db_string_1, '\'', $pos + 1);
		if ($pos === false)
			break;
		$clean .= substr($db_string_1, $old_pos, $pos - $old_pos);

		while (true)
		{
			$pos1 = strpos($db_string_1, '\'', $pos + 1);
			$pos2 = strpos($db_string_1, '\\', $pos + 1);
			if ($pos1 === false)
				break;
			elseif ($pos2 === false || $pos2 > $pos1)
			{
				$pos = $pos1;
				break;
			}

			$pos = $pos2 + 1;
		}
		$clean .= ' %s ';

		$old_pos = $pos + 1;
	}
	$clean .= substr($db_string_1, $old_pos);
	$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $clean)));

	// Comments?  We don't use comments in our queries, we leave 'em outside!
	if (strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, ';') !== false)
		$fail = true;
	// Trying to change passwords, slow us down, or something?
	elseif (strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean) != 0)
		$fail = true;
	elseif (strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
		$fail = true;

	return empty($fail);
}

// ------------------------------------------------------------
// OLD cleaner (slow baseline)
// ------------------------------------------------------------
function smf3_cleaner($db_string)
{
	// Comments that are allowed in a query are preg_removed.
	static $allowed_comments_from = array(
		'~\s+~s',
		'~/\*!40001 SQL_NO_CACHE \*/~',
		'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
		'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
	);
	static $allowed_comments_to = array(
		' ',
		'',
		'',
		'',
	);

	$clean = preg_split('/(?<![\'\\\\])\'(?![\'])/', $db_string);

	for ($i = 0; $i < \count($clean); $i++) {
		if ($i % 2 === 1) {
			$clean[$i] = ' %s ';
		}
	}

	$clean = trim(strtolower(preg_replace(
		$allowed_comments_from,
		$allowed_comments_to,
		implode('', $clean),
	)));

	return !(
		// Empty string?
		$clean === ''
		// Comments?  We don't use comments in our queries, we leave 'em outside!
		|| strpos($clean, '/*') > 2
		|| str_contains($clean, '--')
		|| str_contains($clean, ';')
		// Trying to change passwords, slow us down, or something?
		|| preg_match('~(^|[^a-z])sleep($|[^[_a-z])~s', $clean)
		|| preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean));
}

// ------------------------------------------------------------
// NEW optimized cleaner
// ------------------------------------------------------------
function new_cleaner(string $sql): bool
{
	// Comments that are allowed in a query are preg_removed.
	static $allowed_comments =  '~/\*!(?:40001\s+SQL_NO_CACHE|40000\s+USE\s+INDEX\s+\([A-Za-z_]+?\)|40100\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\s+id_msg\s+=\s+\d+)\s+\*/~';

	// Remove strings to prevent false positives (simpler & faster)
	$clean = preg_replace(
		[
			"/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/s",
			// Strip allowed MySQL optimizer hints
			$allowed_comments,
		],
		["''", ''],
		$sql
	);

	// Trying to change passwords, slow us down, or add comments? We leave 'em outside!
	return !preg_match(
		'/\/\*|--|;|\b(?:sleep|benchmark)\s*\(/i',
		$clean
	);
}

// ------------------------------------------------------------
// Benchmark helper
// ------------------------------------------------------------
function bench(string $label, callable $fn, array $queries, int $iterations)
{
	$totalQueries = $iterations * count($queries);
	$startMem = memory_get_usage(true);
	$start = hrtime(true);

	for ($i = 0; $i < $iterations; $i++) {
		foreach ($queries as $sql) {
			$fn($sql);
		}
	}

	$elapsed = (hrtime(true) - $start) / 1e6; // total ms
	$mem = memory_get_usage(true) - $startMem;
	$avgUs = ($elapsed * 1000) / $totalQueries; // µs per query
	return [$label, $elapsed, $avgUs, $mem, $totalQueries];
}

// ------------------------------------------------------------
// Test dataset
// ------------------------------------------------------------
$queries = [
	// Normal / safe
	"SELECT * FROM users WHERE id = 1",
	"SELECT * FROM users WHERE name='O\\'Connor'",

	// Unsafe / classic
	"SELECT * FROM users; DROP TABLE users;",
	"SELECT * FROM log WHERE msg LIKE '%/*comment*/%';",
	"SELECT SLEEP(2)",
	"SELECT * FROM messages WHERE text='normal' -- trick",
	"SELECT /*!40001 SQL_NO_CACHE */ * FROM posts",
	"INSERT INTO tbl VALUES(1, 'safe')",

	// Edge cases
	"SELECT 'This string has a ; semicolon' AS test",
	"SELECT 'This string has a -- dash' AS test",
	"SELECT 'SLEEP(10)' AS test",
	"SELECT 'benchmark(1000, MD5(1))' AS test",
	"SELECT * FROM /*!40100 ON DUPLICATE KEY UPDATE id_msg = 42 */ tbl",
	"SELECT 'Escaped \\' quote inside' AS test",
	"SELECT * FROM users WHERE name='Alice' AND comment='/* tricky */' -- end",
	"SELECT /*!40000 USE INDEX (idx_test) */ * FROM posts WHERE id=1",
];

// ------------------------------------------------------------
// Scales to test
// ------------------------------------------------------------
$scales = [
	20000,
	200000,
	1000000,
];

$tests = [
	['SMF 2 cleaner', 'smf2_cleaner'],
	['SMF 3 cleaner', 'smf3_cleaner'],
	['New cleaner', 'new_cleaner'],
];

// ------------------------------------------------------------
// Run full benchmark
// ------------------------------------------------------------
foreach ($scales as $total) {
	echo "=== Benchmark for {$total} queries ===\n";
	printf("%-25s : %10s | %12s | %10s\n", '', 'total (ms)', 'avg/query (µs)', 'memory');
	echo str_repeat('-', 68) . "\n";

	foreach ($tests as [$label, $fn]) {
		[$label, $elapsed, $avgUs, $mem] = bench($label, $fn, $queries, (int)($total / count($queries)));
		printf("%-25s : %10.3f | %12.3f | %8.2f MB\n", $label, $elapsed, $avgUs, $mem / 1048576);
	}

	echo "\n";
}

// ------------------------------------------------------------
// Functional difference check
// ------------------------------------------------------------
echo "=== Detection differences ===\n";
foreach ($queries as $sql) {
	$old = smf2_cleaner($sql);
	$new = new_cleaner($sql);
	if ($old !== $new) {
		echo sprintf(
			"- %-60s | old=%s new=%s\n",
			$sql,
			$old ? 'allow' : 'block',
			$new ? 'allow' : 'block'
		);
	}
}


// -------------------- Generate large INSERT --------------------
$table = 'bulk_test';
$columns = ['id', 'payload', 'meta'];
$rows = 50000; // adjust as needed

echo "\n === Generating very large INSERT with {$rows} rows ===\n";
$startGen = microtime(true);

$values = [];
for ($i = 1; $i <= $rows; $i++) {
	$row = [
		$i, // id
		"'" . addslashes("payload_{$i}_" . str_repeat('x', $i % 50)) . "'",
		"'" . addslashes("meta_{$i}_" . str_repeat('y', $i % 30)) . "'"
	];
	$values[] = '(' . implode(', ', $row) . ')';
}

$sql = "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES\n" .
	   implode(",\n", $values);

$genTime = microtime(true) - $startGen;
echo "Generated SQL length: " . number_format(strlen($sql)) . " bytes in " . round($genTime,3) . "s\n";

// -------------------- Run cleaner --------------------

foreach ($tests as [$label, $fn]) {
	echo "\n=== Running $label on the huge INSERT ===\n";
	$startClean = microtime(true);
	$blocked = !$fn($sql);
	$cleanTime = microtime(true) - $startClean;

	echo "Cleaner result: " . ($blocked ? "BLOCKED" : "ALLOWED") . "\n";
	echo "Cleaner runtime: " . round($cleanTime, 3) . " seconds\n";
}
