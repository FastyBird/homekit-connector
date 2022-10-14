<?php declare(strict_types = 1);

// phpcs:ignoreFile

define('FB_TEMP_DIR', __DIR__ . '/tmp');
define('FB_RESOURCES_DIR', __DIR__ . '/../resources');

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Tester using `composer update --dev`';
	exit(1);
}

DG\BypassFinals::enable();
