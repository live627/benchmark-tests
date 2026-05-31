<?php

/**
 * Benchmark regex parsing for extracting defined functions.
 *
 * Usage:
 *   php fns.php /path/to/source
 *
 * Optional:
 *   php fns.php /path/to/source 100
 */

final class FunctionScannerBenchmark
{
	/***********************
	 * Public static methods
	 ***********************/

	public static function getDefinedFunctionsNewRegex(string $file): array
	{
		$source = file_get_contents($file);

		if (!str_contains($source, 'function')) {
			return [];
		}

		// Remove multiline comments so regex does not
		// match fake functions/classes inside them.
		$source = preg_replace('~//[^\h]+|/\*.*?\*/~s', '', $source);
		$functions = [];
		$namespace = '';
		$class = '';

		// token_get_all() is too slow so use a nice little regex instead.
		preg_match_all('/\b(?:namespace\s+((?P>label)(?:\\\(?P>label))*+)\s*;|(?:class\s+((?P>label))(?:[\s,]|\\\\|(?P>label))*+|function\s+((?P>label))\s*\([^)]*\)\s*(?::[^{]+)?){)(?(DEFINE)(?<label>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*+))/i', $source, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			if (!empty($match[1])) {
				$namespace = $match[1] . '\\';
			} elseif (!empty($match[2])) {
				$class = $namespace . $match[2] . '::';
			} elseif (!empty($match[3])) {
				$functions[$class . $match[3]] = $file;
			}
		}

		return $functions;
	}

	public static function getDefinedFunctionsOldRegex(string $file): array
	{
		$source = file_get_contents($file);
		// token_get_all() is too slow so use a nice little regex instead.
		preg_match_all('/\bnamespace\s++((?P>label)(?:\\\(?P>label))*+)\s*+;|\bclass\s++((?P>label))[\w\s]*+{|\bfunction\s++((?P>label))\s*+\(.*\)[:\|\w\s]*+{(?(DEFINE)(?<label>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*+))/i', $source, $matches, PREG_SET_ORDER);

		$functions = [];
		$namespace = '';
		$class = '';

		foreach ($matches as $match) {
			if (!empty($match[1])) {
				$namespace = $match[1] . '\\';
			} elseif (!empty($match[2])) {
				$class = $namespace . $match[2] . '::';
			} elseif (!empty($match[3])) {
				$functions[$class . $match[3]] = $file;
			}
		}

		return $functions;
	}

	public static function benchmark(array $files, int $iterations = 100): void
	{
		$newRegexTime = 0.0;
		$oldRegexTime = 0.0;

		$differences = [];

		// Warmup.
		foreach ($files as $file) {
			self::getDefinedFunctionsOldRegex($file);
			self::getDefinedFunctionsNewRegex($file);
		}

		foreach ($files as $file) {
			$newRegexFunctions = self::getDefinedFunctionsNewRegex($file);
			$oldRegexFunctions = self::getDefinedFunctionsOldRegex($file);

			$newRegexKeys = array_keys($newRegexFunctions);
			$oldRegexKeys = array_keys($oldRegexFunctions);

			sort($newRegexKeys);
			sort($oldRegexKeys);

			$onlyNewRegex = array_diff($newRegexKeys, $oldRegexKeys);
			$onlyOldRegex = array_diff($oldRegexKeys, $newRegexKeys);

			if ($onlyNewRegex !== [] || $onlyOldRegex !== []) {
				$differences[$file] = [
					'new_regex_only' => $onlyNewRegex,
					'old_regex_only' => $onlyOldRegex,
				];
			}
		}

		for ($i = 0; $i < $iterations; $i++) {
			$newRegexCount = 0;
			$oldRegexCount = 0;

			$start = hrtime(true);

			foreach ($files as $file) {
				$newRegexCount += count(self::getDefinedFunctionsNewRegex($file));
			}

			$newRegexTime += (hrtime(true) - $start);

			$start = hrtime(true);

			foreach ($files as $file) {
				$oldRegexCount += count(self::getDefinedFunctionsOldRegex($file));
			}

			$oldRegexTime += (hrtime(true) - $start);
		}

		$newRegexMs = $newRegexTime / 1_000_000;
		$oldRegexMs = $oldRegexTime / 1_000_000;

		printf("Files:        %d\n", count($files));
		printf("Iterations:   %d\n\n", $iterations);

		printf(
			"New regex total: %.3f ms\n",
			$newRegexMs,
		);

		printf(
			"Old regex total: %.3f ms\n\n",
			$oldRegexMs,
		);

		printf(
			"New regex avg:   %.3f ms\n",
			$newRegexMs / $iterations,
		);

		printf(
			"Old regex avg:   %.3f ms\n\n",
			$oldRegexMs / $iterations,
		);

		printf(
			"Speedup:         %.2fx %s\n",
			max($newRegexMs, $oldRegexMs) / min($newRegexMs, $oldRegexMs),
			$newRegexMs < $oldRegexMs ? '(new regex faster)' : '(old regex faster)',
		);

		printf("\n");
		printf("New regex count: %d\n", $newRegexCount);
		printf("Old regex count: %d\n", $oldRegexCount);

		if ($differences === []) {
			printf("\nNo output differences found.\n");

			return;
		}

		printf("\nDifferences detected:\n");

		foreach ($differences as $file => $difference) {
			printf("\n%s\n", $file);

			if ($difference['new_regex_only'] !== []) {
				printf("  New regex only:\n");

				foreach ($difference['new_regex_only'] as $function) {
					printf("    + %s\n", $function);
				}
			}

			if ($difference['old_regex_only'] !== []) {
				printf("  Old regex only:\n");

				foreach ($difference['old_regex_only'] as $function) {
					printf("    - %s\n", $function);
				}
			}
		}
	}
}

function getPhpFiles(string $path): array
{
	if (is_file($path)) {
		return [$path];
	}

	$files = [];

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path),
	);

	foreach ($iterator as $file) {
		if (
			$file->isFile()
			&& strtolower($file->getExtension()) === 'php'
			&& !str_contains($file->getPathname(), 'vendor')
		) {
			$files[] = $file->getPathname();
		}
	}

	return $files;
}

$path = $argv[1] ?? __DIR__;
$iterations = isset($argv[2]) ? (int) $argv[2] : 100;

$files = getPhpFiles($path);

if ($files === []) {
	fwrite(STDERR, "No PHP files found.\n");

	exit(1);
}

FunctionScannerBenchmark::benchmark($files, $iterations);
