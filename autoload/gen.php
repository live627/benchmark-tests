<?php

declare(strict_types=1);

/**
 * Generate PSR-4 compliant PHP class files.
 *
 * @param string $baseDir Base directory where files will be generated.
 * @param string $baseNamespace Root namespace (PSR-4).
 * @param int $count Number of classes to generate.
 * @return void
 */
function generateClasses(string $baseDir, string $baseNamespace, int $count): void
{
	for ($i = 1; $i <= $count; $i++) {
		// Example: App\Generated\Group1\Class1
		$group = 'Group' . (int) ceil($i / 100); // 10 groups of 100
		$className = 'Class' . $i;

		$namespace = $baseNamespace . '\\' . $group;

		// Convert namespace to directory path (PSR-4)
		$dir = $baseDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $namespace);

		if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
			throw new RuntimeException("Failed to create directory: $dir");
		}

		$filePath = $dir . DIRECTORY_SEPARATOR . $className . '.php';

		$content = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

/**
 * Auto-generated class $className
 */
class $className
{
	/**
	 * Example method.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return '$className';
	}
}

PHP;

		file_put_contents($filePath, $content);
	}
}

// --- CONFIG ---
$baseDir = __DIR__ . '/generated';     // output directory
$baseNamespace = 'App\\Generated';     // PSR-4 root namespace
$count = 1000;

// Run
generateClasses($baseDir, $baseNamespace, $count);

echo "Generated $count classes in: $baseDir\n";

/**
 * Build classmap from generated directory.
 *
 * @return array<string, string>
 */
function buildClassMap(string $baseDir, string $baseNamespace): array
{
	$map = [];

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($baseDir)
	);

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$path = $file->getRealPath();

		// Convert path → class (fast path for your generated structure)
		$relative = substr($path, strlen($baseDir) + 1, -4);
		$class = $baseNamespace . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

		$map[$class] = $path;
	}

	return $map;
}

$map = buildClassMap($baseDir, $baseNamespace);
file_put_contents(
	__DIR__ . '/classmap.php',
	'<?php return ' . var_export($map, true) . ';'
);
