<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\Plugin;

class ReviewService extends Component
{
    /**
     * Get approved reviews for an entry, ordered by date DESC.
     */
    public function getReviewsForEntry(Entry|int $entry): array
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;

        return Review::find()
            ->entryId($entryId)
            ->reviewStatus('approved')
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }

    /**
     * Get average rating for an entry (approved reviews only).
     */
    public function getAverageRating(Entry|int $entry): float
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;

        // Use a raw query rather than the element query: ReviewQuery::beforePrepare()
        // overrides the SELECT list, which would clobber an AVG() aggregate.
        $result = (new \craft\db\Query())
            ->from('{{%stars_reviews}}')
            ->innerJoin('{{%elements}}', '[[elements.id]] = [[stars_reviews.id]]')
            ->where([
                'stars_reviews.entryId' => $entryId,
                'stars_reviews.reviewStatus' => 'approved',
                'elements.dateDeleted' => null,
            ])
            ->average('[[stars_reviews.rating]]');

        return $result ? round((float)$result, 1) : 0.0;
    }

    /**
     * Get count of approved reviews for an entry.
     */
    public function getReviewCount(Entry|int $entry): int
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;

        return Review::find()
            ->entryId($entryId)
            ->reviewStatus('approved')
            ->count();
    }

    /**
     * Get rating distribution for an entry (approved reviews only).
     * Returns array like [1 => 3, 2 => 5, 3 => 10, 4 => 20, 5 => 45]
     */
    public function getRatingDistribution(Entry|int $entry): array
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;
        $maxRating = Plugin::getInstance()->getSettings()->maxRating;

        $distribution = [];
        for ($i = 1; $i <= $maxRating; $i++) {
            $distribution[$i] = 0;
        }

        $results = (new \craft\db\Query())
            ->select(['stars_reviews.rating', 'COUNT(*) as cnt'])
            ->from('{{%stars_reviews}}')
            ->innerJoin('{{%elements}}', '[[elements.id]] = [[stars_reviews.id]]')
            ->where([
                'stars_reviews.entryId' => $entryId,
                'stars_reviews.reviewStatus' => 'approved',
                'elements.dateDeleted' => null,
            ])
            ->groupBy('stars_reviews.rating')
            ->all();

        foreach ($results as $row) {
            $distribution[(int)$row['rating']] = (int)$row['cnt'];
        }

        return $distribution;
    }

    /**
     * Save a review element.
     */
    public function saveReview(Review $review): bool
    {
        return Craft::$app->getElements()->saveElement($review);
    }

    /**
     * Approve a review.
     */
    public function approveReview(Review $review): bool
    {
        $review->reviewStatus = 'approved';
        return $this->saveReview($review);
    }

    /**
     * Reject a review.
     */
    public function rejectReview(Review $review): bool
    {
        $review->reviewStatus = 'rejected';
        return $this->saveReview($review);
    }

    /**
     * Mark a review as spam.
     */
    public function markAsSpam(Review $review): bool
    {
        $review->reviewStatus = 'spam';
        return $this->saveReview($review);
    }

    /**
     * Save an admin response to a review.
     */
    public function saveAdminResponse(Review $review, string $response): bool
    {
        $review->adminResponse = $response;
        $review->adminResponseDate = (new \DateTime())->format('Y-m-d H:i:s');
        return $this->saveReview($review);
    }
}
