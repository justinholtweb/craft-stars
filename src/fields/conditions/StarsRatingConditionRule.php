<?php

namespace justinholtweb\stars\fields\conditions;

use craft\fields\conditions\NumberFieldConditionRule;
use justinholtweb\stars\models\RatingSummary;

/**
 * "Rating is ≥ 4" and friends, for a Stars Rating field — compares the stored average.
 *
 * The query side goes through StarsRating::queryCondition() like any field rule; only matching an
 * already-loaded element needs to know the value is a summary object rather than a number.
 */
class StarsRatingConditionRule extends NumberFieldConditionRule
{
    protected function matchFieldValue($value): bool
    {
        if ($value instanceof RatingSummary) {
            $value = $value->average;
        }

        return parent::matchFieldValue($value);
    }

    protected function inputOptions(): array
    {
        return array_merge(parent::inputOptions(), [
            'step' => '0.1',
            'min' => 0,
        ]);
    }
}
