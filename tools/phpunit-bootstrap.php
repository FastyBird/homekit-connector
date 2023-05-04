<?php declare(strict_types = 1);

// phpcs:ignoreFile

if (!defined('FB_TEMP_DIR')) {
	define('FB_TEMP_DIR', __DIR__ . '/../var/tools/PHPUnit/tmp');
}

if (!defined('FB_LOGS_DIR')) {
	define('FB_LOGS_DIR', __DIR__ . '/../var/tools/PHPUnit/logs');
}

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Tester using `composer update --dev`';
	exit(1);
}

DG\BypassFinals::enable();
