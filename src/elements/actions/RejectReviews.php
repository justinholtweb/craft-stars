<?php

namespace justinholtweb\stars\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class RejectReviews extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('stars', 'Reject');
    }

    public function getTriggerLabel(): string
    {
        return static::displayName();
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        foreach ($query->all() as $review) {
            $review->reviewStatus = 'rejected';
            Craft::$app->getElements()->saveElement($review);
        }

        $this->setMessage(Craft::t('stars', 'Reviews rejected.'));
        return true;
    }
}
