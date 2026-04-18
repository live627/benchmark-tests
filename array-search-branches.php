function search_branch_predictable_miss(array $a, $needle): int {
    foreach ($a as $i => $v) {
        if ($v === $needle) { // always false
            return $i;
        }
    }
    return -1;
}

function search_branch_predictable_end(array $a, $needle): int {
    foreach ($a as $i => $v) {
        if ($v === $needle) { // false until last
            return $i;
        }
    }
    return -1;
}

function search_branch_unpredictable(array $a, $needle): int {
    foreach ($a as $i => $v) {
        // random branch — simulates worst-case prediction
        if ((random_int(0, 1) === 1) && $v === $needle) {
            return $i;
        }
    }
    return -1;
}

function search_branch_alternating(array $a, $needle): int {
    $flag = false;

    foreach ($a as $i => $v) {
        $flag = !$flag;

        // alternating true/false pattern
        if ($flag && $v === $needle) {
            return $i;
        }
    }
    return -1;
}

function runBranchPredictionBenchmarks(int $n, int $iterations = 10, int $warmup = 3): void {
    echo "\n=== Branch Prediction Benchmark (" . number_format($n) . ") ===\n";

    $a = [];
    for ($i = 0; $i < $n; $i++) {
        $a[] = randomString();
    }

    $needle_miss = '__definitely_not_found__';
    $needle_end  = $a[$n - 1];

    $cases = [
        'predictable_miss' => ['fn' => 'search_branch_predictable_miss', 'needle' => $needle_miss],
        'predictable_end'  => ['fn' => 'search_branch_predictable_end',  'needle' => $needle_end],
        'unpredictable'    => ['fn' => 'search_branch_unpredictable',    'needle' => $needle_end],
        'alternating'      => ['fn' => 'search_branch_alternating',      'needle' => $needle_end],
    ];

    $results = [];

    foreach ($cases as $label => $case) {
        $fn = $case['fn'];
        $needle = $case['needle'];

        $results[] = benchmark(
            function () use ($fn, $a, $needle) {
                $fn($a, $needle);
            },
            $label,
            $iterations,
            $warmup,
        );
    }

    usort($results, fn($a, $b) => $a['time'] <=> $b['time']);

    echo str_repeat('-', 80) . "\n";
    printf(
        "%-32s | %-13s | %-13s\n",
        'Pattern',
        'Median Time',
        'Avg Time'
    );
    echo str_repeat('-', 80) . "\n";

    foreach ($results as $r) {
        printf(
            "%-32s | %10.3f µs | %10.3f µs\n",
            $r['label'],
            $r['time'] / 1e3,
            $r['time_avg'] / 1e3
        );
    }

    echo str_repeat('-', 80) . "\n";
}

foreach ([2000, 20000, 200000] as $n) {
    runBranchPredictionBenchmarks($n);
}
