<?php

ini_set('memory_limit', '512M');

$tests = [
    'MyFunc',
    '!MyFunc',
    '#MyFunc',
    '!#MyFunc',
    'MyClass::method',
    '!MyClass::method',
    '#MyClass::method',
    'MyClass::method!',
    '$sourcedir/Subs.php|MyClass::method',
    '$sourcedir/Subs.php|!#\\App\\Hooks\\Menu::add',
    'My!Cla#ss::me!thod',
];

$iterations = 50000;

// dummy globals for compatibility
$boarddir = '/board';
$sourcedir = '/source';
$settings = ['theme_dir' => '/theme'];

/**
 * REGEX PARSER
 */
function parse_regex($rawData)
{
    static $regex = '~^
        (?:(?<file>[^|]+)\|)?
        (?<raw>.+)
    $~x';

    preg_match($regex, $rawData, $m);

    $file = $m['file'] ?? '';
    $modFunc = $m['raw'] ?? '';

    $enabled = strpos($modFunc, '!') === false;
    $object  = strpos($modFunc, '#') !== false;

    $modFunc = str_replace(['!', '#'], '', $modFunc);
    $modFunc = ltrim($modFunc, '\\');

    if (strpos($modFunc, '::') !== false) {
        [$class, $method] = explode('::', $modFunc, 2);
        return [$file, $enabled, $object, $class, $method];
    }

    return [$file, $enabled, $object, '', $modFunc];
}

/**
 * ORIGINAL PARSER (faithful)
 */
function parse_manual_original($rawData)
{
    global $boarddir, $sourcedir, $settings;

    $hookData = [
        'object' => false,
        'enabled' => true,
        'absPath' => '',
        'hookFile' => '',
        'pureFunc' => '',
        'method' => '',
        'class' => '',
        'call' => '',
        'rawData' => $rawData,
    ];

    if (empty($rawData))
        return $hookData;

    $modFunc = $rawData;
    $hook = '';

    if (substr($hook, -8) === '_include')
        $modFunc = $modFunc . '|';

    if (strpos($modFunc, '|') !== false)
    {
		list ($hookData['hookFile'], $modFunc) = explode('|', $modFunc);
		$hookData['absPath'] = strtr(strtr(trim($hookData['hookFile']), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir'] ?? '')), '\\', '/');
    }

    if (strpos($modFunc, '#') !== false)
    {
        $modFunc = str_replace('#', '', $modFunc);
        $hookData['object'] = true;
    }

    if ((strpos($modFunc, '!') !== false) || (empty($modFunc) && (strpos($rawData, '!') !== false)))
    {
        $modFunc = str_replace('!', '', $modFunc);
        $hookData['enabled'] = false;
    }

    if (strpos($modFunc, '::') !== false)
    {
        list ($hookData['class'], $hookData['method']) = explode('::', $modFunc);
        $hookData['pureFunc'] = $hookData['method'];
        $hookData['call'] = $modFunc;
    }
    else
        $hookData['call'] = $hookData['pureFunc'] = $modFunc;

    $hookData['call'] = ltrim($hookData['call'], '\\');

    return $hookData;
}

class HookData
{
    public bool $object = false;
    public bool $enabled = true;

    public string $absPath = '';
    public string $hookFile = '';

    public string $pureFunc = '';
    public string $method = '';
    public string $class = '';
    public string $call = '';

    public string $rawData = '';
}

function parse_manual_fast(string $rawData): HookData
{
    global $boarddir, $sourcedir, $settings;

    $h = new HookData();
    $h->rawData = $rawData;

    if ($rawData === '') {
        return $h;
    }

    $pipePos = strpos($rawData, '|');
    $hashPos = strpos($rawData, '#');
    $bangPos = strpos($rawData, '!');

	$h->object = $hashPos !== false;
	$h->enabled = $bangPos === false;

	if ($h->object || !$h->enabled) {
		$rawData = str_replace(['#', '!'], '', $rawData);
	}

    if ($pipePos !== false) {
        $h->hookFile = substr($rawData, 0, $pipePos);
        $func = substr($rawData, $pipePos + 1);

        // Same behavior as original
        $h->absPath = strtr(
            strtr(trim($h->hookFile), [
                '$boarddir'  => $boarddir,
                '$sourcedir' => $sourcedir,
                '$themedir'  => $settings['theme_dir'] ?? '',
            ]),
            '\\',
            '/'
        );
    } else {
        $func = $rawData;
    }

    if (($c = strpos($func, '::')) !== false) {
        $h->class = substr($func, 0, $c);
        $h->method = substr($func, $c + 2);
        $h->pureFunc = $h->method;
        $h->call = $func;
    } else {
        $h->pureFunc = $func;
        $h->call = $func;
    }

    if ($h->call !== '' && $h->call[0] === '\\') {
        $h->call = substr($h->call, 1);
    }

    return $h;
}

/**
 * BENCH
 */
function bench($fn, $tests, $iterations)
{
    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        foreach ($tests as $t) {
            $fn($t);
        }
    }

    return (hrtime(true) - $start) / 1e6;
}

echo "Running {$iterations} iterations...\n";

$regexTime  = bench('parse_regex', $tests, $iterations);
$origTime   = bench('parse_manual_original', $tests, $iterations);
$fastTime   = bench(parse_manual_fast(...), $tests, $iterations);

echo "Regex:           {$regexTime} ms\n";
echo "Manual (orig):   {$origTime} ms\n";
echo "Manual (fast):   {$fastTime} ms\n";

echo "Regex / Orig:    " . ($regexTime / $origTime) . "x\n";
echo "Regex / Fast:    " . ($regexTime / $fastTime) . "x\n";
echo "Orig / Fast:     " . ($origTime / $fastTime) . "x\n";