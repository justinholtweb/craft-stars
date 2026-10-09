<?php

namespace justinholtweb\stars\models;

use craft\base\Model;

/**
 * An entry's stored rating summary: the average of its approved reviews and how many there are.
 *
 * This is the value of a Stars Rating field, what `craft.stars.reviews.summary(entry)` returns, and
 * the `starsRating` GraphQL object. It is read from `{{%stars_ratings}}`, which RatingService keeps
 * current as reviews are approved, rejected, moved or deleted — so reading it costs one indexed row,
 * not an aggregate over every review.
 */
class RatingSummary extends Model
{
    /**
     * The entry (canonical element) this summary belongs to.
     */
    public ?int $elementId = null;

    /**
     * Average rating of approved reviews, to two decimal places. 0 when there are none.
     */
    public float $average = 0.0;

    /**
     * Number of approved reviews.
     */
    public int $count = 0;

    /**
     * The scale the average is out of (the plugin's Max Rating setting).
     */
    public int $maxRating = 5;

    /**
     * The average rounded to one decimal place, as the rest of Stars reports it.
     */
    public function getRounded(): float
    {
        return round($this->average, 1);
    }

    /**
     * The average as a percentage of the scale, for star-fill widths.
     */
    public function getPercent(): float
    {
        return $this->maxRating > 0 ? round($this->average / $this->maxRating * 100, 1) : 0.0;
    }

    public function getHasReviews(): bool
    {
        return $this->count > 0;
    }

    public function __toString(): string
    {
        return $this->count > 0 ? (string)$this->getRounded() : '';
    }
}
