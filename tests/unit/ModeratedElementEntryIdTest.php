<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\stars\elements\Review;

/**
 * Regression coverage for GitHub issue #3: opening a review in the CP and
 * saving (e.g. to add an admin response) was clearing entryId, orphaning the
 * review from its entry.
 *
 * Root cause: Craft's native element editor round-trips the sidebar's
 * elementSelectFieldHtml (`single: true`) entry field through
 * ModeratedElement::setAttributes(). Frontend submission and the bulk
 * moderation actions never touch that method — only a CP save does — which
 * is why only backend edits were affected. When that field's submission came
 * back empty, setAttributes() silently wrote entryId to null with no
 * validation error.
 */
class ModeratedElementEntryIdTest extends Unit
{
    use CreatesReviews;

    protected \UnitTester $tester;

    private int $entryId;

    protected function _before(): void
    {
        // A real elements.id we can legally use as entryId (FK-checked).
        $this->entryId = $this->createReview()->id;
    }

    public function testEmptySubmissionDoesNotClearAnExistingEntryId(): void
    {
        $review = $this->createReview(['entryId' => $this->entryId]);

        // What the CP's entry-select field submits when its selection was
        // never touched but the request body round-trip drops the value.
        $review->setAttributesFromRequest(['entryId' => [], 'reviewStatus' => 'approved']);

        self::assertSame($this->entryId, $review->entryId);
    }

    public function testChangingTheSelectionStillUpdatesEntryId(): void
    {
        $review = $this->createReview(['entryId' => $this->entryId]);
        $otherEntryId = $this->createReview()->id;

        $review->setAttributesFromRequest(['entryId' => [(string)$otherEntryId]]);

        self::assertSame($otherEntryId, $review->entryId);
    }

    public function testNewElementCanStillBeCreatedWithoutAnEntryId(): void
    {
        $review = new Review();
        $review->reviewerName = 'Anonymous';
        $review->rating = 5;
        $review->reviewStatus = 'pending';

        $review->setAttributesFromRequest(['entryId' => []]);

        self::assertNull($review->entryId);
    }
}
