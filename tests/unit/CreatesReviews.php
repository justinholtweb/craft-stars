<?php

namespace justinholtweb\stars\tests\unit;

use Craft;
use justinholtweb\stars\elements\Review;
use RuntimeException;

/**
 * Helper for spinning up real Review elements against the test database.
 */
trait CreatesReviews
{
    /**
     * Create and save a Review element. Returns the saved element (with a real
     * elements.id), so its id can be reused as an `entryId` foreign key.
     */
    protected function createReview(array $attributes = []): Review
    {
        $review = new Review();
        $review->reviewerName = $attributes['reviewerName'] ?? 'Test Reviewer';
        $review->rating = $attributes['rating'] ?? 5;
        $review->reviewStatus = $attributes['reviewStatus'] ?? 'approved';

        foreach ($attributes as $key => $value) {
            $review->$key = $value;
        }

        if (!Craft::$app->getElements()->saveElement($review, false)) {
            throw new RuntimeException(
                'Failed to save Review: ' . implode('; ', $review->getFirstErrors())
            );
        }

        return $review;
    }
}
