<?php

namespace justinholtweb\stars\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class MarkAsSpam extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('stars', 'Mark as Spam');
    }

    public function getTriggerLabel(): string
    {
        return static::displayName();
    }

    public function getTriggerHtml(): ?string
    {
        return null;
    }

    public function getConfirmationMessage(): ?string
    {
        return Craft::t('stars', 'Are you sure you want to mark the selected reviews as spam?');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        foreach ($query->all() as $review) {
            $review->reviewStatus = 'spam';
            Craft::$app->getElements()->saveElement($review);
        }

        $this->setMessage(Craft::t('stars', 'Reviews marked as spam.'));
        return true;
    }
}
