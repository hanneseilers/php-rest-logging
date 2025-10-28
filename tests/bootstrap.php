<?php
// Basic bootstrap for tests: include Composer autoload if available and project files
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
	require_once $autoloader;
}

// Include lightweight project bootstrap for tests (Database etc.)
require_once __DIR__ . '/../storage.php';
