<?php declare(strict_types = 1);

// phpcs:ignoreFile

use Ninjify\Nunjuck\Environment;

define('FB_TEMP_DIR', __DIR__ . '/tmp');
define('FB_RESOURCES_DIR', __DIR__ . '/../resources');

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}

Tester\Environment::bypassFinals();

// Configure environment
Environment::setupTester();
Environment::setupTimezone('UTC');
Environment::setupVariables(__DIR__);
