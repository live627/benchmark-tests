<?php

declare(strict_types=1);

$modes = [
	'custom_require',
	'custom_once',
	'classlist',
	'classlist-once',
	'classlist-authoritative',
	'classlist-authoritative-once',
	'composer',
	'composer-optimized',
	'composer-authoritative',
];

$count = 1000;
$iterations = 5;

foreach ($modes as $mode) {
	foreach ([0, 1] as $random) {
		$label = $mode . ($random ? ' (random)' : ' (sequential)');

		$times = [];

		for ($i = 0; $i < $iterations; $i++) {
			$cmd = sprintf(
				'php %s/worker.php %s %d %d',
				__DIR__,
				$mode,
				$count,
				$random
			);
			exec($cmd, $lines, $exitCode);

			$lastLine = trim(end($lines));
			$times[] = (int) $lastLine;
		}

		$avg = array_sum($times) / count($times);

		printf("%-50s %.2f ms\n", $label, $avg / 1e6);
	}
}