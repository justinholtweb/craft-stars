<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\CommentService;

/**
 * Covers comment threading: depth calculation, the max-depth cap enforced on
 * save, and tree assembly.
 */
class ThreadingTest extends Unit
{
    use CreatesComments;

    protected \UnitTester $tester;

    private CommentService $comments;
    private int $entryId;

    protected function _before(): void
    {
        $this->comments = Plugin::getInstance()->comments;
        Plugin::getInstance()->getSettings()->maxCommentDepth = 2;

        // Host element to hang the thread on.
        $host = $this->createComment(['commentStatus' => 'pending']);
        $this->entryId = $host->id;
    }

    public function testDepthIsComputedFromAncestors(): void
    {
        $top = $this->createComment(['entryId' => $this->entryId, 'commentStatus' => 'approved']);
        self::assertSame(1, $top->getDepth());

        $reply = $this->createComment(['entryId' => $this->entryId, 'parentId' => $top->id, 'commentStatus' => 'approved']);
        self::assertSame(2, $reply->getDepth());
    }

    public function testRepliesBeyondMaxDepthArePromoted(): void
    {
        $top = $this->createComment(['entryId' => $this->entryId, 'commentStatus' => 'approved']);
        $reply = $this->createComment(['entryId' => $this->entryId, 'parentId' => $top->id, 'commentStatus' => 'approved']);

        // Replying to a depth-2 comment (the cap) should attach alongside it.
        $deep = $this->createComment(['entryId' => $this->entryId, 'parentId' => $reply->id, 'commentStatus' => 'approved']);

        self::assertSame($top->id, $deep->parentId);
        self::assertSame(2, $deep->getDepth());
    }

    public function testMaxDepthOfOneDisablesReplies(): void
    {
        Plugin::getInstance()->getSettings()->maxCommentDepth = 1;

        $top = $this->createComment(['entryId' => $this->entryId, 'commentStatus' => 'approved']);
        $reply = $this->createComment(['entryId' => $this->entryId, 'parentId' => $top->id, 'commentStatus' => 'approved']);

        // With no replies allowed, the "reply" is promoted to top-level.
        self::assertNull($reply->parentId);
        self::assertSame(1, $reply->getDepth());
    }

    public function testMissingParentBecomesTopLevel(): void
    {
        $orphan = $this->createComment(['entryId' => $this->entryId, 'parentId' => 999999, 'commentStatus' => 'approved']);
        self::assertNull($orphan->parentId);
    }

    public function testGetCommentTreeNestsReplies(): void
    {
        $parent = $this->createComment(['entryId' => $this->entryId, 'commentStatus' => 'approved', 'body' => 'parent']);
        $this->createComment(['entryId' => $this->entryId, 'parentId' => $parent->id, 'commentStatus' => 'approved', 'body' => 'reply a']);
        $this->createComment(['entryId' => $this->entryId, 'parentId' => $parent->id, 'commentStatus' => 'approved', 'body' => 'reply b']);

        // A pending reply must not appear in the tree.
        $this->createComment(['entryId' => $this->entryId, 'parentId' => $parent->id, 'commentStatus' => 'pending']);

        $tree = $this->comments->getCommentTree($this->entryId);

        self::assertCount(1, $tree);
        self::assertSame($parent->id, $tree[0]->id);
        self::assertCount(2, $tree[0]->children);
        self::assertContainsOnlyInstancesOf(Comment::class, $tree[0]->children);
    }
}
