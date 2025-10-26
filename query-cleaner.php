<?php

/**
 * SMF Query Cleaning Benchmark (Old vs Optimized)
 * Includes per-query average time (µs/query)
 */
$GLOBALS['_last_clean'] = []; // global store for latest $clean strings

// ------------------------------------------------------------
// SMF 2.1.6 cleaner (baseline)
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
	if (str_contains($db_string, '\'\''))
		$db_string_1 = str_replace('\'\'', '', $db_string);
	else
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
	$GLOBALS['_last_clean'][__FUNCTION__] = $clean;

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
// SMF 2.1.7 cleaner
// ------------------------------------------------------------
function smf217_cleaner($db_string)
{
	// Comments that are allowed in a query are preg_removed.
	static $allowed_comments_from = array(
		'~(?<![\'\\\\])\'\X*?(?<![\'\\\\])\'~',
		'~\s+~s',
		'~/\*!40001 SQL_NO_CACHE \*/~',
		'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
		'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
	);

	static $allowed_comments_to = array(
		' %s ',
		' ',
		'',
		'',
		'',
	);

	$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $db_string)));

	$GLOBALS['_last_clean'][__FUNCTION__] = $clean;

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
// sbulen cleaner
// ------------------------------------------------------------
function sbulen_cleaner($db_string)
{
	// Comments that are allowed in a query are preg_removed.
	static $allowed_comments_from = array(
		'~\'\X*?\'~s',
		'~\s+~s',
		'~/\*!40001 SQL_NO_CACHE \*/~',
		'~/\*!40000 USE INDEX \([A-Za-z\_]+?\) \*/~',
		'~/\*!40100 ON DUPLICATE KEY UPDATE id_msg = \d+ \*/~',
	);

	static $allowed_comments_to = array(
		' %s ',
		' ',
		'',
		'',
		'',
	);

	// Clear out escaped backslashes & single quotes first, to make it simpler to ID & remove string literals
	if (str_contains($db_string, '\'\''))
		$clean = str_replace('\'\'', '', $db_string);
	else
		$clean = str_replace(array('\\\\', '\\\''), array('', ''), $db_string);
	$clean = trim(strtolower(preg_replace($allowed_comments_from, $allowed_comments_to, $clean)));

	$GLOBALS['_last_clean'][__FUNCTION__] = $clean;

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
// SMF 3 cleaner
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
	$GLOBALS['_last_clean'][__FUNCTION__] = $clean;

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
function live627_cleaner($db_string)
{
	// Comments that are allowed in a query are preg_removed.
	static $allowed_comments =  '~/\*!(?:40001\s+SQL_NO_CACHE|40000\s+USE\s+INDEX\s+\([A-Za-z_]+?\)|40100\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\s+id_msg\s+=\s+\d+)\s+\*/~';

	// Clear out escaped backslashes & single quotes first, to make it simpler to ID & remove string literals
	// Remove escaped sequences inside SQL string literals
	$string = str_contains($db_string, '\'\'') ? '/\'\'/' : '/\\\\\'|\\\\\\\\/';
	//~ if (str_contains($db_string, '\'\''))
		//~ $clean = str_replace('\'\'', '', $db_string);
	//~ else
		//~ $clean = str_replace(array('\\\\', '\\\''), array('', ''), $db_string);
	$clean = preg_replace(
		[$string, '/\'[^\']+\'/', $allowed_comments],
		['', '%s', ''],
		$db_string
	);

	$GLOBALS['_last_clean'][__FUNCTION__] = $clean;

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
	memory_reset_peak_usage();
	$start = hrtime(true);

	for ($i = 0; $i < $iterations; $i++) {
		foreach ($queries as $sql) {
			$fn($sql);
		}
	}

	$elapsed = (hrtime(true) - $start) / 1e6; // total ms
	$mem = memory_get_peak_usage();
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
	"SELECT 'Escaped \' quote inside\\\\\';' AS test", // mysql
	"SELECT 'Escaped '' quote inside''\\'';' AS test", // pgsql
	"SELECT * FROM users WHERE name='Alice' AND comment='/* tricky */' -- end",
	"SELECT /*!40000 USE INDEX (idx_test) */ * FROM posts WHERE id=1",
	"            INSERT INTO smf_personal_messages(\"id_pm_head\", \"id_member_from\", \"deleted_by_sender\", \"from_name\", \"msgtime\", \"subject\", \"body\")
            VALUES
                (0, 1, 0, SUBSTRING('admin', 1, 255), 1761181726, SUBSTRING('Hello world'' \\', 1, 255), SUBSTRING('I&#39;ll', 1, 65534)) RETURNING id_pm",
	"				SELECT s.code, f.filename, s.description
				FROM smf_smileys AS s
					JOIN smf_smiley_files AS f ON (s.id_smiley = f.id_smiley)
				WHERE f.smiley_set = 'fugue'
					AND s.code IN ('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\\'(', ':-\\\\', '^-^', 'O0', 'C:-)', 'O:-)')",
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
	['SMF 2.1.6 cleaner', 'smf2_cleaner'],
	['SMF 2.1.7 cleaner', 'smf217_cleaner'],
	['sbulen cleaner', 'sbulen_cleaner'],
	['SMF 3 cleaner', 'smf3_cleaner'],
	['live627 cleaner', 'live627_cleaner'],
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
foreach ($tests as [$label, $fn]) {
	if ($fn == 'smf2_cleaner') {
		continue;
	}

	echo "=== Detection differences: $label ===\n";

	foreach ($queries as $sql) {
		$old = smf2_cleaner($sql);
		$new = $fn($sql);

		if ($old !== $new) {
			echo sprintf(
				"- %-60s | old=%s new=%s\n",
				$sql,
				$old ? 'allow' : 'block',
				$new ? 'allow' : 'block'
			);
		}
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

	memory_reset_peak_usage();
	$mem = -memory_get_peak_usage();
	$startClean = microtime(true);
	$blocked = !$fn($sql);
	$cleanTime = microtime(true) - $startClean;
	$mem += memory_get_peak_usage();

	echo "Cleaner result: " . ($blocked ? "BLOCKED" : "ALLOWED") . "\n";
	echo "Cleaner runtime: " . round($cleanTime, 3) . " seconds\n";
	printf("Peak memory: %.2f MB\n", $mem / 1048576);
}

echo "\n=== Comparing normalized \$clean outputs ===\n";

foreach ($queries as $sql) {
	echo "\nQuery:\n$sql\n";

	foreach ($tests as [$label, $fn]) {
		$fn($sql);
	}

	$cleans = $GLOBALS['_last_clean'];
	$GLOBALS['_last_clean'] = []; // reset for next query

	$ref = reset($cleans);
	$diffs = array_filter($cleans, fn($v) => strtolower($v) !== strtolower($ref));

	if (empty($diffs)) {
		echo "✅ All cleaners produced identical \$clean values.\n";
	} else {
		echo "⚠ Differences found:\n";
		foreach ($cleans as $name => $val) {
			printf("- %-20s: %s\n", $name, $val);
		}
	}
}
