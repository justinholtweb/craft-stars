<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use Craft;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\NotificationService;
use justinholtweb\stars\services\ReviewService;
use justinholtweb\stars\services\SchemaService;
use justinholtweb\stars\services\SpamService;

/**
 * Smoke test: confirms the plugin installs into a booted Craft app, its
 * migration runs, and its service components are wired up.
 */
class PluginInstallTest extends Unit
{
    protected \UnitTester $tester;

    public function testPluginIsInstalled(): void
    {
        $plugin = Plugin::getInstance();
        self::assertInstanceOf(Plugin::class, $plugin);
        self::assertTrue(Craft::$app->getPlugins()->isPluginInstalled('stars'));
    }

    public function testReviewsTableWasCreated(): void
    {
        self::assertTrue(Craft::$app->getDb()->tableExists('{{%stars_reviews}}'));
    }

    public function testServiceComponentsAreRegistered(): void
    {
        $plugin = Plugin::getInstance();
        self::assertInstanceOf(ReviewService::class, $plugin->reviews);
        self::assertInstanceOf(SpamService::class, $plugin->spam);
        self::assertInstanceOf(SchemaService::class, $plugin->schema);
        self::assertInstanceOf(NotificationService::class, $plugin->notifications);
    }
}
