<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use justinholtweb\stars\elements\Comment;

class CommentService extends Component
{
    /**
     * Get approved comments for an entry, newest first.
     */
    public function getCommentsForEntry(Entry|int $entry): array
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;

        return Comment::find()
            ->entryId($entryId)
            ->commentStatus('approved')
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }

    /**
     * Get count of approved comments for an entry.
     */
    public function getCommentCount(Entry|int $entry): int
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;

        return Comment::find()
            ->entryId($entryId)
            ->commentStatus('approved')
            ->count();
    }

    /**
     * Get approved comments for an entry as a nested tree: an array of
     * top-level comments, each with its replies attached to `->children`
     * (recursively). Built from a single flat query.
     *
     * @return Comment[]
     */
    public function getCommentTree(Entry|int $entry): array
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;

        /** @var Comment[] $all */
        $all = Comment::find()
            ->entryId($entryId)
            ->commentStatus('approved')
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        // Index by id and reset any stale children.
        $byId = [];
        foreach ($all as $comment) {
            $comment->children = [];
            $byId[$comment->id] = $comment;
        }

        // Attach each comment to its parent, or collect it as a root.
        $roots = [];
        foreach ($all as $comment) {
            if ($comment->parentId !== null && isset($byId[$comment->parentId])) {
                $byId[$comment->parentId]->children[] = $comment;
            } else {
                // Top-level, or a reply whose parent isn't approved/visible.
                $roots[] = $comment;
            }
        }

        return $roots;
    }

    /**
     * Get approved replies to a comment, oldest first.
     */
    public function getReplies(Comment|int $comment): array
    {
        $commentId = $comment instanceof Comment ? $comment->id : $comment;

        return Comment::find()
            ->parentId($commentId)
            ->commentStatus('approved')
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();
    }

    public function saveComment(Comment $comment): bool
    {
        return Craft::$app->getElements()->saveElement($comment);
    }

    public function approveComment(Comment $comment): bool
    {
        $comment->commentStatus = 'approved';
        return $this->saveComment($comment);
    }

    public function rejectComment(Comment $comment): bool
    {
        $comment->commentStatus = 'rejected';
        return $this->saveComment($comment);
    }

    public function markAsSpam(Comment $comment): bool
    {
        $comment->commentStatus = 'spam';
        return $this->saveComment($comment);
    }
}
