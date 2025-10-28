<?php
// Basic bootstrap for tests: include Composer autoload if available and project files
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
	require_once $autoloader;
}

// Include lightweight project bootstrap for tests (Database etc.)
require_once __DIR__ . '/../storage.php';

// During tests, convert deprecations and notices to exceptions to make them visible
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
	// Convert deprecations and notices into exceptions so PHPUnit reports them with traces
	if ($severity & (E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_NOTICE)) {
		throw new \ErrorException($message, 0, $severity, $file, $line);
	}

	// Let other errors through to the normal handler
	return false;
});
