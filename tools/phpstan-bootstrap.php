<?php declare(strict_types = 1);

use DG\BypassFinals;

if (!defined('FB_APP_DIR')) {
	define('FB_APP_DIR', 'val');
}

if (!defined('FB_RESOURCES_DIR')) {
	define('FB_RESOURCES_DIR', 'val');
}

if (!defined('FB_TEMP_DIR')) {
	define('FB_TEMP_DIR', 'val');
}

if (!defined('FB_LOGS_DIR')) {
	define('FB_LOGS_DIR', 'val');
}

if (!defined('FB_CONFIG_DIR')) {
	define('FB_CONFIG_DIR', 'val');
}

BypassFinals::enable();
