<?php
/**
 * Craft application config for the test suite.
 *
 * Returns the standard test app config assembled by Craft's test framework.
 * Database credentials are read from the environment (provided by DDEV).
 */

use craft\test\TestSetup;

return TestSetup::createTestCraftObjectConfig();
