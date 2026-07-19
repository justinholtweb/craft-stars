<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use Craft;
use justinholtweb\stars\elements\actions\Approve;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\CommentService;

/**
 * Covers the Comment element, its query filters, CommentService, and the
 * generalized moderation action operating on a non-Review element.
 */
class CommentTest extends Unit
{
    use CreatesComments;

    protected \UnitTester $tester;

    private CommentService $comments;

    protected function _before(): void
    {
        $this->comments = Plugin::getInstance()->comments;
    }

    public function testCommentsTableWasCreated(): void
    {
        self::assertTrue(Craft::$app->getDb()->tableExists('{{%stars_comments}}'));
    }

    public function testCommentElementIsRegistered(): void
    {
        self::assertContains(Comment::class, Craft::$app->getElements()->getAllElementTypes());
    }

    public function testSaveAndReloadComment(): void
    {
        $comment = $this->createComment(['authorName' => 'Jo', 'body' => 'Nice post']);
        self::assertNotNull($comment->id);

        $loaded = Comment::find()->id($comment->id)->one();
        self::assertInstanceOf(Comment::class, $loaded);
        self::assertSame('Jo', $loaded->authorName);
        self::assertSame('Nice post', $loaded->body);
    }

    public function testCustomStatusMapsToElementStatus(): void
    {
        $comment = $this->createComment(['commentStatus' => 'pending']);
        self::assertSame('pending', $comment->getStatus());
    }

    public function testGetCommentsForEntryReturnsApprovedOnly(): void
    {
        $host = $this->createComment(['commentStatus' => 'pending']);
        $entryId = $host->id;

        $this->createComment(['entryId' => $entryId, 'commentStatus' => 'approved']);
        $this->createComment(['entryId' => $entryId, 'commentStatus' => 'approved']);
        $this->createComment(['entryId' => $entryId, 'commentStatus' => 'pending']);
        $this->createComment(['entryId' => $entryId, 'commentStatus' => 'spam']);

        self::assertCount(2, $this->comments->getCommentsForEntry($entryId));
        self::assertSame(2, $this->comments->getCommentCount($entryId));
    }

    public function testTopLevelFilterAndReplies(): void
    {
        $host = $this->createComment(['commentStatus' => 'pending']);
        $entryId = $host->id;

        $parent = $this->createComment(['entryId' => $entryId, 'commentStatus' => 'approved', 'body' => 'parent']);
        $this->createComment(['entryId' => $entryId, 'parentId' => $parent->id, 'commentStatus' => 'approved', 'body' => 'reply 1']);
        $this->createComment(['entryId' => $entryId, 'parentId' => $parent->id, 'commentStatus' => 'approved', 'body' => 'reply 2']);

        $topLevel = Comment::find()->entryId($entryId)->topLevel()->commentStatus('approved')->all();
        self::assertCount(1, $topLevel);
        self::assertSame($parent->id, $topLevel[0]->id);

        $replies = $this->comments->getReplies($parent->id);
        self::assertCount(2, $replies);
    }

    public function testModerationHelpers(): void
    {
        $comment = $this->createComment(['commentStatus' => 'pending']);

        self::assertTrue($this->comments->approveComment($comment));
        self::assertSame('approved', $comment->commentStatus);

        self::assertTrue($this->comments->rejectComment($comment));
        self::assertSame('rejected', $comment->commentStatus);

        self::assertTrue($this->comments->markAsSpam($comment));
        self::assertSame('spam', $comment->commentStatus);
    }

    public function testGeneralizedApproveActionWorksOnComments(): void
    {
        $host = $this->createComment(['commentStatus' => 'pending']);
        $entryId = $host->id;
        $a = $this->createComment(['entryId' => $entryId, 'commentStatus' => 'pending']);
        $b = $this->createComment(['entryId' => $entryId, 'commentStatus' => 'pending']);

        $action = new Approve();
        self::assertTrue($action->performAction(Comment::find()->entryId($entryId)));

        self::assertSame('approved', Comment::find()->id($a->id)->one()->commentStatus);
        self::assertSame('approved', Comment::find()->id($b->id)->one()->commentStatus);
    }
}
