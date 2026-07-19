<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\stars\twig\CommentsVariable;

/**
 * Covers the craft.comments Twig API.
 */
class CommentsVariableTest extends Unit
{
    use CreatesComments;

    protected \UnitTester $tester;

    private CommentsVariable $var;

    protected function _before(): void
    {
        $this->var = new CommentsVariable();
    }

    public function testForEntryReturnsApprovedOnly(): void
    {
        $host = $this->createComment(['commentStatus' => 'pending']);
        $entryId = $host->id;

        $a = $this->createComment(['entryId' => $entryId, 'commentStatus' => 'approved']);
        $b = $this->createComment(['entryId' => $entryId, 'commentStatus' => 'approved']);
        $this->createComment(['entryId' => $entryId, 'commentStatus' => 'pending']);
        $this->createComment(['entryId' => $entryId, 'commentStatus' => 'spam']);

        $ids = array_map(fn($c) => $c->id, $this->var->forEntry($entryId)->all());

        self::assertCount(2, $ids);
        self::assertContains($a->id, $ids);
        self::assertContains($b->id, $ids);
    }

    public function testCountApprovedOnly(): void
    {
        $host = $this->createComment(['commentStatus' => 'pending']);
        $entryId = $host->id;

        $this->createComment(['entryId' => $entryId, 'commentStatus' => 'approved']);
        $this->createComment(['entryId' => $entryId, 'commentStatus' => 'spam']);

        self::assertSame(1, $this->var->count($entryId));
    }

    public function testTopLevelExcludesReplies(): void
    {
        $host = $this->createComment(['commentStatus' => 'pending']);
        $entryId = $host->id;

        $parent = $this->createComment(['entryId' => $entryId, 'commentStatus' => 'approved']);
        $this->createComment(['entryId' => $entryId, 'parentId' => $parent->id, 'commentStatus' => 'approved']);

        $top = $this->var->topLevel($entryId)->all();
        self::assertCount(1, $top);
        self::assertSame($parent->id, $top[0]->id);

        self::assertCount(1, $this->var->replies($parent->id));
    }
}
