<?php

namespace justinholtweb\stars\tests\unit;

use Craft;
use justinholtweb\stars\elements\Comment;
use RuntimeException;

/**
 * Helper for spinning up real Comment elements against the test database.
 */
trait CreatesComments
{
    protected function createComment(array $attributes = []): Comment
    {
        $comment = new Comment();
        $comment->authorName = $attributes['authorName'] ?? 'Test Author';
        $comment->body = $attributes['body'] ?? 'A comment body.';
        $comment->commentStatus = $attributes['commentStatus'] ?? 'approved';

        foreach ($attributes as $key => $value) {
            $comment->$key = $value;
        }

        if (!Craft::$app->getElements()->saveElement($comment, false)) {
            throw new RuntimeException(
                'Failed to save Comment: ' . implode('; ', $comment->getFirstErrors())
            );
        }

        return $comment;
    }
}
