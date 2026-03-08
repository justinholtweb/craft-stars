<?php

namespace justinholtweb\stars\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class ApproveReviews extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('stars', 'Approve');
    }

    public function getTriggerLabel(): string
    {
        return static::displayName();
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        foreach ($query->all() as $review) {
            $review->reviewStatus = 'approved';
            Craft::$app->getElements()->saveElement($review);
        }

        $this->setMessage(Craft::t('stars', 'Reviews approved.'));
        return true;
    }
}
