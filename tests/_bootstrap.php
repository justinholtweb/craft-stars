<?php
/**
 * Test bootstrap for the Stars plugin.
 *
 * Boots a real Craft CMS application (via the craft\test\Craft Codeception
 * module) against the database provided by DDEV, then installs the plugin so
 * its migration runs and its element queries work against real tables.
 */

use craft\test\TestSetup;

ini_set('date.timezone', 'UTC');

// Define the paths Craft's test framework expects.
define('CRAFT_TESTS_PATH', __DIR__);
define('CRAFT_ROOT_PATH', dirname(__DIR__));
define('CRAFT_STORAGE_PATH', __DIR__ . '/_craft/storage');
define('CRAFT_TEMPLATES_PATH', __DIR__ . '/_craft/templates');
define('CRAFT_CONFIG_PATH', __DIR__ . '/_craft/config');
define('CRAFT_MIGRATIONS_PATH', __DIR__ . '/_craft/migrations');
define('CRAFT_TRANSLATIONS_PATH', __DIR__ . '/_craft/translations');
define('CRAFT_VENDOR_PATH', dirname(__DIR__) . '/vendor');

TestSetup::configureCraft();
