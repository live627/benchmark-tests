<?php

declare(strict_types=1);

ini_set('memory_limit', '512M');

const ITERATIONS = 1_000_000;

class TestClass
{
    public static function staticMethod(int $x): int
    {
        return $x;
    }

    public function instanceMethod(int $x): int
    {
        return $x;
    }

    public function __invoke(int $x): int
    {
        return $x;
    }
}

function testFunction(int $x): int
{
    return $x;
}

// Setup
$obj = new TestClass();
$method = 'instanceMethod';
$staticMethod = 'staticMethod';
$params = [123];

// Build all callable forms
$callables = [
    'function string'            => 'testFunction',
    'built-in string'           => 'is_int',
    'static string'             => 'TestClass::staticMethod',
    'static array'              => [TestClass::class, 'staticMethod'],
    'instance array'            => [$obj, 'instanceMethod'],
    'closure'                   => function ($x) { return $x; },
    'arrow function'            => fn($x) => $x,
    'first-class function'      => testFunction(...),
    'first-class static'        => TestClass::staticMethod(...),
    'first-class instance'      => $obj->instanceMethod(...),
    'invokable object'          => $obj,
    'closure from callable'     => \Closure::fromCallable([$obj, 'instanceMethod']),
    'variable function'         => ($fn = 'testFunction') ? $fn : null,
    'dynamic array method'      => [$obj, $method],
    'first-class dynamic'       => $obj->$method(...),
    'static dynamic array'      => [TestClass::class, $staticMethod],
];

// Warmup (important for JIT/opcache)
foreach ($callables as $c) {
    for ($i = 0; $i < 1000; $i++) {
        $c(...$params);
    }
}

// Benchmark
$results = [];

foreach ($callables as $name => $callable) {
    $start = hrtime(true);

    for ($i = 0; $i < ITERATIONS; $i++) {
        $callable(...$params);
    }

    $end = hrtime(true);
    $timeMs = ($end - $start) / 1e6;

    $results[$name] = $timeMs;
}

// Sort results fastest → slowest
asort($results);

// Output
echo "Iterations: " . ITERATIONS . PHP_EOL;
echo str_repeat('-', 40) . PHP_EOL;

foreach ($results as $name => $time) {
    printf("%-30s %10.3f ms\n", $name, $time);
}
