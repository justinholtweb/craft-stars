<?php

namespace justinholtweb\stars\elements\db;

use craft\helpers\Db;
use justinholtweb\stars\elements\base\ModeratedQuery;

class ReviewQuery extends ModeratedQuery
{
    // entryId and ipAddress params live on ModeratedQuery.
    public ?int $rating = null;
    public ?int $minRating = null;
    public ?int $maxRating = null;
    public ?string $reviewerName = null;
    public ?string $reviewerEmail = null;
    public ?string $reviewStatus = null;

    public function rating(?int $value): static
    {
        $this->rating = $value;
        return $this;
    }

    public function minRating(?int $value): static
    {
        $this->minRating = $value;
        return $this;
    }

    public function maxRating(?int $value): static
    {
        $this->maxRating = $value;
        return $this;
    }

    public function reviewerName(?string $value): static
    {
        $this->reviewerName = $value;
        return $this;
    }

    public function reviewerEmail(?string $value): static
    {
        $this->reviewerEmail = $value;
        return $this;
    }

    public function reviewStatus(?string $value): static
    {
        $this->reviewStatus = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('stars_reviews');

        $this->query->select([
            'stars_reviews.entryId',
            'stars_reviews.rating',
            'stars_reviews.reviewText',
            'stars_reviews.reviewerName',
            'stars_reviews.reviewerEmail',
            'stars_reviews.pros',
            'stars_reviews.cons',
            'stars_reviews.adminResponse',
            'stars_reviews.adminResponseDate',
            'stars_reviews.ipAddress',
            'stars_reviews.userAgent',
            'stars_reviews.submissionUrl',
            'stars_reviews.reviewStatus',
        ]);

        if ($this->entryId !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_reviews.entryId', $this->entryId));
        }

        if ($this->rating !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_reviews.rating', $this->rating));
        }

        if ($this->minRating !== null) {
            $this->subQuery->andWhere(['>=', 'stars_reviews.rating', $this->minRating]);
        }

        if ($this->maxRating !== null) {
            $this->subQuery->andWhere(['<=', 'stars_reviews.rating', $this->maxRating]);
        }

        if ($this->reviewerName !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_reviews.reviewerName', $this->reviewerName));
        }

        if ($this->reviewerEmail !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_reviews.reviewerEmail', $this->reviewerEmail));
        }

        if ($this->reviewStatus !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_reviews.reviewStatus', $this->reviewStatus));
        }

        if ($this->ipAddress !== null) {
            $this->subQuery->andWhere(Db::parseParam('stars_reviews.ipAddress', $this->ipAddress));
        }

        return parent::beforePrepare();
    }

    protected function statusColumn(): string
    {
        return 'stars_reviews.reviewStatus';
    }
}
