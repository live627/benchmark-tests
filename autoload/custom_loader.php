<?php

declare(strict_types=1);

function registerCustomLoader(string $prefix, string $baseDir, bool $once, array $classMap = [], bool $classMapAuthoritative = false): void
{
	spl_autoload_register(function ($class) use ($prefix, $baseDir, $once, $classMap, $classMapAuthoritative) {
		static $missingClasses = [];

		// class map lookup
		if (isset($classMap[$class])) {
			if ($once) {
				require_once $classMap[$class];
			} else {
				require $classMap[$class];
			}

			return;
		}

		if ($classMapAuthoritative || isset($missingClasses[$class])) {
			return false;
		}

		if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
			return;
		}

		$relative = substr($class, strlen($prefix));
		$file = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';

		if (is_file($file)) {
			if ($once) {
				require_once $file;
			} else {
				require $file;
			}
		} elseif ($classMapAuthoritative) {
			// Remember that this class does not exist.
			$missingClasses[$class] = true;
		}
	});
}