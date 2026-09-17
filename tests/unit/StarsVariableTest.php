<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use craft\web\twig\variables\CraftVariable;
use justinholtweb\stars\twig\CommentsVariable;
use justinholtweb\stars\twig\ReviewsVariable;
use justinholtweb\stars\twig\StarsVariable;
use yii\base\Event;

/**
 * Covers how the plugin claims Twig variable names: everything under
 * `craft.stars`, with the legacy `craft.reviews` / `craft.comments` aliases
 * yielding to any other plugin that already registered them. (#4)
 */
class StarsVariableTest extends Unit
{
    protected \UnitTester $tester;

    public function testStarsVariableExposesBothApis(): void
    {
        $var = new StarsVariable();

        self::assertInstanceOf(ReviewsVariable::class, $var->getReviews());
        self::assertInstanceOf(CommentsVariable::class, $var->getComments());
    }

    public function testRegistersNamespacedAndLegacyVariables(): void
    {
        $craft = new CraftVariable();

        self::assertInstanceOf(StarsVariable::class, $craft->get('stars'));
        self::assertInstanceOf(ReviewsVariable::class, $craft->get('reviews'));
        self::assertInstanceOf(CommentsVariable::class, $craft->get('comments'));
    }

    public function testDoesNotClobberAVariableAnotherPluginClaimed(): void
    {
        $other = new \stdClass();

        // Prepended, so it runs before the plugin's own EVENT_INIT handler —
        // standing in for a plugin like verbb/comments registering first.
        $handler = function (Event $event) use ($other) {
            $event->sender->set('comments', $other);
        };

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, $handler, null, false);

        try {
            $craft = new CraftVariable();
        } finally {
            Event::off(CraftVariable::class, CraftVariable::EVENT_INIT, $handler);
        }

        self::assertSame($other, $craft->get('comments'));
        self::assertInstanceOf(
            CommentsVariable::class,
            $craft->get('stars')->getComments()
        );
    }
}
