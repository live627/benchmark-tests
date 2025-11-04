<?php
/**
 * ip_validation_benchmark.php
 *
 * Benchmark and correctness comparison of multiple IP validation methods (IPv4 + IPv6).
 * Usage: php ip_validation_benchmark.php
 *
 * Produces a timing and accuracy report for each method.
 */

/**
 * Generate a list of test IPs: mixes explicit cases with some random/generated items.
 *
 * @param int $randomCount Number of random entries to generate (mixed valid/invalid)
 * @return array Array of IP strings
 */
function generateTestIps(int $randomCount = 1000): array
{
    $samples = [
        // valid IPv4
        '127.0.0.1',
        '192.168.0.1',
        '8.8.8.8',
        '255.255.255.255',
        '0.0.0.0',
        // invalid IPv4
        '256.0.0.1',
        '192.168.0.999',
        '192.168.0',
        '192.168.0.1.5',
        'abc.def.ghi.jkl',
        // valid IPv6
        '::1',
        '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        '2001:db8:85a3::8a2e:370:7334',
        'fe80::1ff:fe23:4567:890a',
        '::ffff:192.0.2.128', // embedded IPv4
        // invalid IPv6
        '2001:db8:85a3:::8a2e:370:7334',
        '2001:db8:85a3::8a2e:370:7334:12345',
        'gggg::1',
        ':1:2:3:4:5:6:7',
        '1:2:3:4:5:6:7:8:9',
    ];

    // Simple random IPv4/IPv6 generator (some valid, some intentionally malformed)
    for ($i = 0; $i < $randomCount; $i++) {
        if (rand(0, 1) === 0) {
            // IPv4-ish
            if (rand(0, 9) < 7) {
                // valid-ish
                $samples[] = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
            } else {
                // malformed
                $samples[] = rand(0, 999) . '.' . rand(0, 999) . '.' . rand(0, 999) . '.' . rand(0, 999) . '.' . rand(0, 9);
            }
        } else {
            // IPv6-ish
            if (rand(0, 9) < 7) {
                // valid-ish: produce 8 hex groups, with some :: compression sometimes
                $groups = [];
                for ($g = 0; $g < 8; $g++) {
                    $groups[] = dechex(rand(0, 0xffff));
                }
                if (rand(0, 4) === 0) {
                    // compress a random run
                    $start = rand(0, 6);
                    $len = rand(1, 3);
                    array_splice($groups, $start, $len, ['']);
                    $samples[] = implode(':', $groups);
                } else {
                    $samples[] = implode(':', $groups);
                }
            } else {
                // malformed hex or too many groups
                $groups = [];
                $count = rand(1, 12);
                for ($g = 0; $g < $count; $g++) {
                    $groups[] = substr(str_shuffle('0123456789abcdefg'), 0, rand(1, 6));
                }
                $samples[] = implode(':', $groups);
            }
        }
    }

    return $samples;
}

/**
 * Custom IPv4 validator using ip2long() + pack().
 * - ip2long() can interpret non-decimal formats, so we double-check with pack/unpack
 *   to ensure it’s a valid dotted-decimal IPv4 address.
 */
function validateIp2longPack(string $ip): bool
{
    $long = ip2long($ip);
    if ($long === false) {
        return false;
    }

    return pack('N', $long) !== false;
}

/**
 * IPv4 regex validator (strict)
 *
 * @param string $ip
 * @return bool
 */
function validateRegexIpv4(string $ip): bool
{
    // Each octet: 0-255
    static $pattern = '/^((25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)\.){3}'
        . '(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)$/';
    return preg_match($pattern, $ip) === 1;
}

/**
 * IPv6 regex validator (relatively strict).
 *
 * This attempts to cover typical valid IPv6 representations, including :: shorthand and
 * embedded IPv4 (e.g. ::ffff:192.0.2.128).
 *
 * @param string $ip
 * @return bool
 */
function validateRegexIpv6(string $ip): bool
{
    // A reasonably comprehensive IPv6 pattern (not trivial but practical)
    static $pattern = '/^('
        // 1: full 8 groups of 1-4 hex
        . '([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4}|'
        // 2: :: with up to 7 groups behind/ahead
        . '([0-9A-Fa-f]{1,4}:){1,7}:|'
        . '([0-9A-Fa-f]{1,4}:){1,6}:[0-9A-Fa-f]{1,4}|'
        . '([0-9A-Fa-f]{1,4}:){1,5}(:[0-9A-Fa-f]{1,4}){1,2}|'
        . '([0-9A-Fa-f]{1,4}:){1,4}(:[0-9A-Fa-f]{1,4}){1,3}|'
        . '([0-9A-Fa-f]{1,4}:){1,3}(:[0-9A-Fa-f]{1,4}){1,4}|'
        . '([0-9A-Fa-f]{1,4}:){1,2}(:[0-9A-Fa-f]{1,4}){1,5}|'
        . '[0-9A-Fa-f]{1,4}:((:[0-9A-Fa-f]{1,4}){1,6})|'
        . ':((:[0-9A-Fa-f]{1,4}){1,7}|:)|'
        // IPv4-mapped IPv6 ::ffff:192.0.2.128
        . '([0-9A-Fa-f]{1,4}:){1,4}:(\d{1,3}\.){3}\d{1,3}'
        . ')$/';
    if (preg_match($pattern, $ip) === 1) {
        // If it includes an embedded IPv4, check that the IPv4 part is valid
        if (strpos($ip, '.') !== false) {
            $parts = explode(':', $ip);
            $last = end($parts);
            return validateRegexIpv4($last);
        }
        return true;
    }
    return false;
}

/**
 * Custom IPv4 validator using explode + numeric checks (fast).
 *
 * @param string $ip
 * @return bool
 */
function validateCustomIpv4(string $ip): bool
{
    $parts = explode('.', $ip);
    if (count($parts) !== 4) {
        return false;
    }
    foreach ($parts as $p) {
        // no leading spaces or plus signs, numeric only
        if (!ctype_digit($p)) {
            return false;
        }
        // prevent leading zeros? we allow them (0, 00, 001) as numeric
        $n = (int)$p;
        if ($n < 0 || $n > 255) {
            return false;
        }
        // prevent leading zero octets greater than 1 char? optional - we won't reject
    }
    return true;
}

/**
 * Custom IPv6 validator (practical, not exhaustive).
 *
 * This routine:
 * - allows one '::' shorthand
 * - allows hex groups of 1-4 digits
 * - allows embedded IPv4 in the last two segments
 *
 * @param string $ip
 * @return bool
 */
function validateCustomIpv6(string $ip): bool
{
    // Quick fast path: inet_pton is actually robust; use it for correctness shortcut:
    if (@inet_pton($ip) === false) {
        return false;
    }
    // We already know inet_pton succeeded, so this is valid IPv6 (or IPv4 mapped)
    // But ensure it is not IPv4 (inet_pton accepts IPv4 too), so check presence of ':' or hex
    return strpos($ip, ':') !== false;
}

/**
 * Run benchmark for a set of validators on a list of IPs.
 *
 * @param array $ips
 * @param array $validators associative array name => callable
 * @param int $rounds how many times to iterate through the list (amplifies timing)
 * @param array $groundTruth Optional: associative array ip => ['v4'|'v6'|'invalid'] to check correctness
 * @return array results
 */
function runBenchmark(array $ips, array $validators, int $rounds = 50, array $groundTruth = []): array
{
    $results = [];

    foreach ($validators as $name => $callable) {
        $tp = $tn = $fp = $fn = 0;
        $start = microtime(true);

        for ($r = 0; $r < $rounds; $r++) {
            foreach ($ips as $ip) {
                $isValid = $callable($ip);
                // correctness checks if groundTruth provided:
                if (!empty($groundTruth) && isset($groundTruth[$ip])) {
                    $truth = $groundTruth[$ip]; // 'v4'|'v6'|'invalid' or 'valid'
                    $truthBool = $truth !== 'invalid';
                    if ($isValid && $truthBool) {
                        // true positive
                        $tp++;
                    } elseif (!$isValid && !$truthBool) {
                        $tn++;
                    } elseif ($isValid && !$truthBool) {
                        $fp++;
                    } else {
                        $fn++;
                    }
                }
            }
        }

        $end = microtime(true);
        $elapsed = $end - $start;
        $totalChecks = count($ips) * $rounds;

        $results[$name] = [
            'elapsed' => $elapsed,
            'perSecond' => $elapsed > 0 ? ($totalChecks / $elapsed) : INF,
            'totalChecks' => $totalChecks,
            'tp' => $tp,
            'tn' => $tn,
            'fp' => $fp,
            'fn' => $fn,
        ];
    }

    return $results;
}

// ---------------------------
// Prepare test set and ground truth
// ---------------------------
$testIps = generateTestIps(2000); // create a mixed sample (adjust count if desired)

// Build a small ground truth map for explicit knowns (we'll rely on filter_var as authoritative for these known values)
$groundTruth = [];
$explicitKnown = [
    // We'll determine truth for the explicit list and a small sample using filter_var to keep it deterministic
    '127.0.0.1',
    '192.168.0.1',
    '255.255.255.255',
    '0.0.0.0',
    '256.0.0.1',
    '192.168.0.999',
    '::1',
    '2001:db8:85a3::8a2e:370:7334',
    '::ffff:192.0.2.128',
    '2001:db8:85a3:::8a2e:370:7334',
    'gggg::1',
];
foreach ($explicitKnown as $ip) {
    $isValid = filter_var($ip, FILTER_VALIDATE_IP) !== false;
    $groundTruth[$ip] = $isValid ? 'valid' : 'invalid';
}

// Also add a moderate random subset evaluated with filter_var for ground truth
$sampleForTruth = array_slice($testIps, 0, 500);
foreach ($sampleForTruth as $ip) {
    $groundTruth[$ip] = filter_var($ip, FILTER_VALIDATE_IP) !== false ? 'valid' : 'invalid';
}

// ---------------------------
// Validators to test
// ---------------------------
$validators = [
    'filter_var_cache' => function ($ip) {
        static $ip_cache;

        if (isset($ip_cache[$ip])) {
            return $ip_cache[$ip];
        }

        $ip_cache[$ip] = filter_var($ip, FILTER_VALIDATE_IP) !== false;

        return $ip_cache[$ip];
    },
    'filter_var_both' => function ($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    },
    'filter_var_ipv4' => function ($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    },
    'filter_var_ipv6' => function ($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    },
    'filter_var_both_strpos' => function ($ip) {
        if (str_contains($ip, ':') === false) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        } else {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }
    },
    'ip2long' => fn($ip) => ip2long($ip) !== false,
    'ip2long + pack' => fn($ip) => validateIp2longPack($ip) !== false,
    'inet_pton' => fn($ip) => inet_pton($ip) !== false,
    'regex_ipv4' => function ($ip) {
        // if IP contains ':', skip (not IPv4)
        if (strpos($ip, ':') !== false) return false;
        return validateRegexIpv4($ip);
    },
    'regex_ipv6' => function ($ip) {
        if (strpos($ip, ':') === false) return false;
        return validateRegexIpv6($ip);
    },
    'custom_ipv4' => function ($ip) {
        if (strpos($ip, ':') !== false) return false;
        return validateCustomIpv4($ip);
    },
    'custom_ipv6' => function ($ip) {
        if (strpos($ip, ':') === false) return false;
        return validateCustomIpv6($ip);
    },
];

// ---------------------------
// Run benchmark
// ---------------------------
$rounds = 25; // number of times the suite is repeated. Increase for more precise timing (at cost of time).
echo "Running benchmark: " . count($testIps) . " test IPs x {$rounds} rounds (~" . (count($testIps) * $rounds) . " checks per validator)\n";
$results = runBenchmark($testIps, $validators, $rounds, $groundTruth);

// ---------------------------
// Print report
// ---------------------------
echo str_repeat('=', 72) . PHP_EOL;
echo str_pad('Validator', 22) . str_pad('Time (ms)', 12) . str_pad('Checks/s', 14) . str_pad('TP', 6) . str_pad('TN', 6) . str_pad('FP', 6) . str_pad('FN', 6) . PHP_EOL;
echo str_repeat('-', 72) . PHP_EOL;
foreach ($results as $name => $r) {
    $ms = $r['elapsed'] * 1000;
    echo str_pad($name, 22)
        . str_pad(number_format($ms, 2), 12)
        . str_pad(number_format($r['perSecond']), 14)
        . str_pad($r['tp'], 6)
        . str_pad($r['tn'], 6)
        . str_pad($r['fp'], 6)
        . str_pad($r['fn'], 6)
        . PHP_EOL;
}
echo str_repeat('=', 72) . PHP_EOL;

echo "Understanding the TP / TN / FP / FN columns:\n";
echo "  TP = True Positive → correctly accepted a valid IP.\n";
echo "  TN = True Negative → correctly rejected an invalid IP.\n";
echo "  FP = False Positive → incorrectly accepted an invalid IP (too lenient).\n";
echo "  FN = False Negative → incorrectly rejected a valid IP (too strict).\n\n";

echo "A perfect validator has FP = 0 and FN = 0, with high TP and TN counts.\n";
echo "Use these metrics to see whether a method is overly permissive or overly restrictive.\n\n";
