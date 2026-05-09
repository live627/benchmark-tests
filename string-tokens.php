<?php

declare(strict_types=1);

ini_set('memory_limit', '2048M');

const ITERATIONS = 200;
const WARMUP = 20;
const TOKEN_COUNT = 1000;
const DELIMITER = ',';

/**
 * ============================
 * STATISTICS
 * ============================
 */
function mean(array $v): float
{
	return array_sum($v) / count($v);
}

function median(array $v): float
{
	sort($v);
	$n = count($v);
	$m = intdiv($n, 2);

	return $n % 2 ? $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
}

function percentile(array $v, float $p): float
{
	sort($v);
	return $v[(int) ceil(($p / 100) * count($v)) - 1];
}

/**
 * ============================
 * INPUT GENERATION
 * ============================
 */
function generateInput(int $count): string
{
	$tokens = [];

	for ($i = 0; $i < $count; $i++) {
		$tokens[] = "token_$i";
	}

	return implode(DELIMITER, $tokens);
}

/**
 * ============================
 * CURSOR INTERFACE CONTRACT
 * ============================
 */
interface TokenCursor
{
	public function next(): ?string;
}

/**
 * ============================
 * BENCHMARK CORE
 * ============================
 */
function benchmarkCursor(string $label, callable $factory, string $input): array
{
	// warmup
	for ($i = 0; $i < WARMUP; $i++) {
		$cursor = $factory($input);
		while ($cursor->next() !== null) {}
	}

	$times = [];
	$peakMem = 0;
	$lastCount = 0;

	for ($i = 0; $i < ITERATIONS; $i++) {
		gc_collect_cycles();

		$startMem = memory_get_peak_usage();
		$start = hrtime(true);

		$cursor = $factory($input);

		$count = 0;
		while (($token = $cursor->next()) !== null) {
			$count++;
		}

		$end = hrtime(true);
		$endMem = memory_get_peak_usage();

		$times[] = ($end - $start) / 1_000_000;
		$peakMem = max($peakMem, $endMem - $startMem);
		$lastCount = $count;
	}

	return [
		'label' => $label,
		'mean_ms' => mean($times),
		'median_ms' => median($times),
		'p95_ms' => percentile($times, 95),
		'min_ms' => min($times),
		'max_ms' => max($times),
		'peak_mb' => $peakMem / 1024,
		'tokens' => $lastCount,
	];
}

/**
 * ============================
 * TOKENIZER CURSORS
 * ============================
 */

function explodeCursor(string $input): TokenCursor
{
	$parts = explode(DELIMITER, $input);
	$i = 0;

	return new class($parts, $i) implements TokenCursor {
		public function __construct(
			private array $parts,
			private int $i
		) {}

		public function next(): ?string
		{
			return $this->parts[$this->i++] ?? null;
		}
	};
}

function strposCursor(string $input): TokenCursor
{
	return new class($input) implements TokenCursor {
		public function __construct(private string $input)
		{
			$this->offset = 0;
		}

		private int $offset;

		public function next(): ?string
		{
			if ($this->offset < 0) {
				return null;
			}

			$pos = strpos($this->input, DELIMITER, $this->offset);

			if ($pos === false) {
				$token = substr($this->input, $this->offset);
				$this->offset = -1;
				return $token;
			}

			$token = substr(
				$this->input,
				$this->offset,
				$pos - $this->offset
			);

			$this->offset = $pos + 1;

			return $token;
		}
	};
}

function strcspnCursor(string $input): TokenCursor
{
	return new class($input) implements TokenCursor {
		public function __construct(private string $input)
		{
			$this->offset = 0;
			$this->len = strlen($input);
		}

		private int $offset;
		private int $len;

		public function next(): ?string
		{
			if ($this->offset >= $this->len) {
				return null;
			}

			$span = strcspn($this->input, DELIMITER, $this->offset);

			$token = substr(
				$this->input,
				$this->offset,
				$span
			);

			$this->offset += $span + 1;

			return $token;
		}
	};
}

function manualCursor(string $input): TokenCursor
{
	return new class($input) implements TokenCursor {
		public function __construct(private string $input)
		{
			$this->len = strlen($input);
			$this->i = 0;
			$this->start = 0;
		}

		private int $len;
		private int $i;
		private int $start;

		public function next(): ?string
		{
			while ($this->i < $this->len) {
				if ($this->input[$this->i] === DELIMITER) {
					$token = substr(
						$this->input,
						$this->start,
						$this->i - $this->start
					);

					$this->start = $this->i + 1;
					$this->i++;

					return $token;
				}

				$this->i++;
			}

			if ($this->start <= $this->len) {
				$token = substr($this->input, $this->start);
				$this->start = $this->len + 1;
				return $token;
			}

			return null;
		}
	};
}

function strtokCursor(string $input): TokenCursor
{
	return new class($input) implements TokenCursor {
		public function __construct(private string $input)
		{
			$this->started = false;
		}

		private bool $started;

		public function next(): ?string
		{
			if (!$this->started) {
				$this->started = true;
				$token = strtok($this->input, DELIMITER);
			} else {
				$token = strtok(DELIMITER);
			}

			return $token === false ? null : $token;
		}
	};
}

function pregSplitCursor(string $input): TokenCursor
{
	$parts = preg_split('/' . preg_quote(DELIMITER, '/') . '/', $input);
	$i = 0;

	return new class($parts, $i) implements TokenCursor {
		public function __construct(
			private array $parts,
			private int $i
		) {}

		public function next(): ?string
		{
			return $this->parts[$this->i++] ?? null;
		}
	};
}

function simdChunkCursor(string $input, int $chunkSize = 256): TokenCursor
{
	return new class($input, $chunkSize) implements TokenCursor {
		public function __construct(
			private string $input,
			private int $chunkSize
		) {
			$this->offset = 0;
			$this->buffer = '';
			$this->len = strlen($input);
		}

		private int $offset;
		private string $buffer;
		private int $len;

		public function next(): ?string
		{
			if ($this->offset >= $this->len && $this->buffer === '') {
				return null;
			}

			while (true) {
				$pos = strpos($this->input, DELIMITER, $this->offset);

				if ($pos === false) {
					$token = $this->buffer . substr($this->input, $this->offset);
					$this->offset = $this->len;
					$this->buffer = '';
					return $token;
				}

				$token = $this->buffer . substr(
					$this->input,
					$this->offset,
					$pos - $this->offset
				);

				$this->offset = $pos + 1;
				$this->buffer = '';

				return $token;
			}
		}
	};
}

function zeroAllocCursor(string $input): TokenCursor
{
	return new class($input) implements TokenCursor {
		public function __construct(private string $input)
		{
			$this->offset = 0;
			$this->len = strlen($input);
		}

		private int $offset;
		private int $len;

		public function next(): ?string
		{
			if ($this->offset > $this->len) {
				return null;
			}

			$pos = strpos($this->input, DELIMITER, $this->offset);

			if ($pos === false) {
				$token = substr($this->input, $this->offset);
				$this->offset = $this->len + 1;
				return $token;
			}

			$token = substr(
				$this->input,
				$this->offset,
				$pos - $this->offset
			);

			$this->offset = $pos + 1;

			return $token;
		}
	};
}

function generatorCursor(string $input): TokenCursor
{
	return new class($input) implements TokenCursor {
		public function __construct(private string $input)
		{
			$this->gen = $this->create();
			$this->gen->next(); // prime generator
		}

		private Generator $gen;

		private function create(): Generator
		{
			$offset = 0;

			while (($pos = strpos($this->input, DELIMITER, $offset)) !== false) {
				yield substr($this->input, $offset, $pos - $offset);
				$offset = $pos + 1;
			}

			yield substr($this->input, $offset);
		}

		public function next(): ?string
		{
			if (!$this->gen->valid()) {
				return null;
			}

			$value = $this->gen->current();
			$this->gen->next();

			return $value;
		}
	};
}

/**
 * ============================
 * RUNNER
 * ============================
 */
function run(): void
{
	$input = generateInput(TOKEN_COUNT);

	$results = [];

	$results[] = benchmarkCursor('explode', 'explodeCursor', $input);
	$results[] = benchmarkCursor('strpos', 'strposCursor', $input);
	$results[] = benchmarkCursor('strcspn', 'strcspnCursor', $input);
	$results[] = benchmarkCursor('manual', 'manualCursor', $input);
	$results[] = benchmarkCursor('strtok', 'strtokCursor', $input);
	$results[] = benchmarkCursor('preg_split', 'pregSplitCursor', $input);
	$results[] = benchmarkCursor('simd chunk', 'simdChunkCursor', $input);
	$results[] = benchmarkCursor('zero alloc', 'zeroAllocCursor', $input);
	$results[] = benchmarkCursor('generator', 'generatorCursor', $input);

	usort($results, fn($a, $b) => $a['mean_ms'] <=> $b['mean_ms']);

	echo str_pad("Method", 16)
		. str_pad("Mean", 10)
		. str_pad("Median", 10)
		. str_pad("P95", 10)
		. str_pad("Min", 10)
		. str_pad("Max", 10)
		. str_pad("Mem KB", 10)
		. PHP_EOL;

	echo str_repeat("-", 80) . PHP_EOL;

	foreach ($results as $r) {
		echo str_pad($r['label'], 16)
			. str_pad(number_format($r['mean_ms'], 3), 10)
			. str_pad(number_format($r['median_ms'], 3), 10)
			. str_pad(number_format($r['p95_ms'], 3), 10)
			. str_pad(number_format($r['min_ms'], 3), 10)
			. str_pad(number_format($r['max_ms'], 3), 10)
			. str_pad(number_format($r['peak_mb'], 3), 10)
			. PHP_EOL;
	}
}

run();
