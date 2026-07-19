<?php

namespace justinholtweb\stars\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use justinholtweb\stars\elements\base\ModeratedElement;

/**
 * Bulk-reject any ModeratedElement (Reviews, Comments).
 */
class Reject extends ElementAction
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
        foreach ($query->all() as $element) {
            /** @var ModeratedElement $element */
            $element->{$element::statusAttribute()} = 'rejected';
            Craft::$app->getElements()->saveElement($element);
        }

        $this->setMessage(Craft::t('stars', '{name} rejected.', [
            'name' => $query->elementType::pluralDisplayName(),
        ]));
        return true;
    }
}
