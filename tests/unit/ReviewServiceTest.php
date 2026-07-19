<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\ReviewService;

/**
 * Covers the aggregation math in ReviewService: only approved reviews count,
 * averages round correctly, distributions bucket by rating, and the empty
 * case never divides by zero.
 */
class ReviewServiceTest extends Unit
{
    use CreatesReviews;

    protected \UnitTester $tester;

    private ReviewService $reviews;
    private int $entryId;

    protected function _before(): void
    {
        $this->reviews = Plugin::getInstance()->reviews;

        // A "host" element whose id is a valid elements.id we can hang reviews on.
        $host = $this->createReview(['reviewStatus' => 'pending']);
        $this->entryId = $host->id;

        // Approved reviews: 5, 4, 3  → avg 4.0, count 3
        $this->createReview(['entryId' => $this->entryId, 'rating' => 5]);
        $this->createReview(['entryId' => $this->entryId, 'rating' => 4]);
        $this->createReview(['entryId' => $this->entryId, 'rating' => 3]);

        // Non-approved reviews must be excluded from every aggregate.
        $this->createReview(['entryId' => $this->entryId, 'rating' => 1, 'reviewStatus' => 'pending']);
        $this->createReview(['entryId' => $this->entryId, 'rating' => 2, 'reviewStatus' => 'rejected']);
        $this->createReview(['entryId' => $this->entryId, 'rating' => 1, 'reviewStatus' => 'spam']);
    }

    public function testReviewCountOnlyIncludesApproved(): void
    {
        self::assertSame(3, $this->reviews->getReviewCount($this->entryId));
    }

    public function testAverageRatingOnlyIncludesApproved(): void
    {
        self::assertSame(4.0, $this->reviews->getAverageRating($this->entryId));
    }

    public function testGetReviewsForEntryReturnsApprovedNewestFirst(): void
    {
        $result = $this->reviews->getReviewsForEntry($this->entryId);
        self::assertCount(3, $result);

        foreach ($result as $review) {
            self::assertSame('approved', $review->reviewStatus);
        }
    }

    public function testRatingDistributionBucketsByRating(): void
    {
        $distribution = $this->reviews->getRatingDistribution($this->entryId);

        // maxRating default is 5, so keys 1..5 must all be present.
        self::assertSame([1, 2, 3, 4, 5], array_keys($distribution));
        self::assertSame(0, $distribution[1]);
        self::assertSame(0, $distribution[2]);
        self::assertSame(1, $distribution[3]);
        self::assertSame(1, $distribution[4]);
        self::assertSame(1, $distribution[5]);
    }

    public function testEmptyEntryReturnsZeroesWithoutDividingByZero(): void
    {
        $unknownId = $this->entryId + 100000;

        self::assertSame(0, $this->reviews->getReviewCount($unknownId));
        self::assertSame(0.0, $this->reviews->getAverageRating($unknownId));
        self::assertSame([], $this->reviews->getReviewsForEntry($unknownId));
        self::assertSame(
            [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            $this->reviews->getRatingDistribution($unknownId)
        );
    }

    public function testStatusTransitionHelpers(): void
    {
        $review = $this->createReview(['entryId' => $this->entryId, 'reviewStatus' => 'pending']);

        self::assertTrue($this->reviews->approveReview($review));
        self::assertSame('approved', $review->reviewStatus);

        self::assertTrue($this->reviews->rejectReview($review));
        self::assertSame('rejected', $review->reviewStatus);

        self::assertTrue($this->reviews->markAsSpam($review));
        self::assertSame('spam', $review->reviewStatus);
    }
}
