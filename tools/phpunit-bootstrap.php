<?php declare(strict_types = 1);

// phpcs:ignoreFile

define('FB_APP_DIR', realpath(__DIR__ . '/..'));
define('FB_CONFIG_DIR', realpath(__DIR__ . '/../config'));
define('FB_VENDOR_DIR', realpath(__DIR__ . '/../vendor'));
is_string(getenv('TEST_TOKEN'))
	? define('FB_TEMP_DIR', __DIR__ . '/../var/tools/PHPUnit/tmp/' . getmypid() . '-' . md5((string) time()) . '-' . getenv('TEST_TOKEN') ?? '')
	: define('FB_TEMP_DIR', __DIR__ . '/../var/tools/PHPUnit/tmp/' . getmypid() . '-' . md5((string) time()));
is_string(getenv('TEST_TOKEN'))
	? define('FB_LOGS_DIR', __DIR__ . '/../var/tools/PHPUnit/logs/' . getmypid() . '-' . md5((string) time()) . '-' . getenv('TEST_TOKEN') ?? '')
	: define('FB_LOGS_DIR', __DIR__ . '/../var/tools/PHPUnit/logs/' . getmypid() . '-' . md5((string) time()));

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Tester using `composer update --dev`';
	exit(1);
}

DG\BypassFinals::enable();
