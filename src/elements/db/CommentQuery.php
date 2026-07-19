<?php

namespace justinholtweb\stars\elements\db;

use craft\helpers\Db;
use justinholtweb\stars\elements\base\ModeratedQuery;

class CommentQuery extends ModeratedQuery
{
    // entryId and ipAddress params live on ModeratedQuery.
    public ?string $commentStatus = null;
    public ?int $parentId = null;
    public bool $topLevel = false;
    public ?string $authorName = null;
    public ?string $authorEmail = null;

    public function commentStatus(?string $value): static
    {
        $this->commentStatus = $value;
        return $this;
    }

    public function parentId(?int $value): static
    {
        $this->parentId = $value;
        return $this;
    }

    /**
     * Limit to top-level comments (no parent).
     */
    public function topLevel(bool $value = true): static
    {
        $this->topLevel = $value;
        return $this;
    }

    public function authorName(?string $value): static
    {
        $this->authorName = $value;
        return $this;
    }

    public function authorEmail(?string $value): static
    {
        $this->authorEmail = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('stars_comments');

        $this->query->select([
            'stars_comments.entryId',
            'stars_comments.parentId',
            'stars_comments.authorUserId',
            'stars_comments.authorName',
            'stars_comments.authorEmail',
            'stars_comments.body',
            'stars_comments.commentStatus',
            'stars_comments.ipAddress',
            'stars_comments.userAgent',
            'stars_comments.submissionUrl',
        ]);

        if ($this->entryId !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_comments.entryId', $this->entryId));
        }

        if ($this->topLevel) {
            $this->subQuery->andWhere(['stars_comments.parentId' => null]);
        } elseif ($this->parentId !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_comments.parentId', $this->parentId));
        }

        if ($this->commentStatus !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_comments.commentStatus', $this->commentStatus));
        }

        if ($this->authorName !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_comments.authorName', $this->authorName));
        }

        if ($this->authorEmail !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_comments.authorEmail', $this->authorEmail));
        }

        if ($this->ipAddress !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_comments.ipAddress', $this->ipAddress));
        }

        return parent::beforePrepare();
    }

    protected function statusColumn(): string
    {
        return 'stars_comments.commentStatus';
    }
}
