<?php

namespace justinholtweb\stars\twig;

use craft\elements\Entry;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\elements\db\CommentQuery;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\captcha\CaptchaProviderFactory;

/**
 * `craft.comments` Twig API.
 */
class CommentsVariable
{
    /**
     * Approved comments for an entry, oldest first (conversation order).
     */
    public function forEntry(Entry|int $entry): CommentQuery
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;

        return Comment::find()
            ->entryId($entryId)
            ->commentStatus('approved')
            ->orderBy(['dateCreated' => SORT_ASC]);
    }

    /**
     * Approved top-level comments for an entry (no replies), oldest first.
     */
    public function topLevel(Entry|int $entry): CommentQuery
    {
        return $this->forEntry($entry)->topLevel();
    }

    /**
     * Approved comments for an entry as a nested tree (each comment exposes
     * its replies via `.children`).
     *
     * @return Comment[]
     */
    public function tree(Entry|int $entry): array
    {
        return Plugin::getInstance()->comments->getCommentTree($entry);
    }

    /**
     * Approved replies to a comment, oldest first.
     */
    public function replies(Comment|int $comment): array
    {
        return Plugin::getInstance()->comments->getReplies($comment);
    }

    /**
     * Count of approved comments for an entry.
     */
    public function count(Entry|int $entry): int
    {
        return Plugin::getInstance()->comments->getCommentCount($entry);
    }

    /**
     * The active captcha provider + site key for rendering the widget, or null.
     *
     * @return array{provider: string, siteKey: string}|null
     */
    public function captcha(): ?array
    {
        return CaptchaProviderFactory::frontendConfig(Plugin::getInstance()->getSettings());
    }
}
