<?php

namespace justinholtweb\stars\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use justinholtweb\stars\elements\base\ModeratedElement;

/**
 * Bulk-approve any ModeratedElement (Reviews, Comments).
 */
class Approve extends ElementAction
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
        foreach ($query->all() as $element) {
            /** @var ModeratedElement $element */
            $element->{$element::statusAttribute()} = 'approved';
            Craft::$app->getElements()->saveElement($element);
        }

        $this->setMessage(Craft::t('stars', '{name} approved.', [
            'name' => $query->elementType::pluralDisplayName(),
        ]));
        return true;
    }
}
