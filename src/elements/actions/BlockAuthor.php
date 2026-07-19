<?php

namespace justinholtweb\stars\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use justinholtweb\stars\elements\base\ModeratedElement;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\BlockService;

/**
 * Add the authors (email + IP) of the selected Reviews/Comments to the
 * blocklist, and mark their submissions as spam.
 */
class BlockAuthor extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('stars', 'Block Author');
    }

    public function getTriggerLabel(): string
    {
        return static::displayName();
    }

    public function getConfirmationMessage(): ?string
    {
        return Craft::t('stars', 'Block the authors of the selected items? Their email and IP will be added to the blocklist and their submissions marked as spam.');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $block = Plugin::getInstance()->block;
        $reason = Craft::t('stars', 'Blocked from the {name} index', [
            'name' => $query->elementType::lowerDisplayName(),
        ]);
        $userId = Craft::$app->getUser()->getId();

        foreach ($query->all() as $element) {
            /** @var ModeratedElement $element */
            $email = $element->getAuthorEmail();
            if (!empty($email)) {
                $block->block(BlockService::TYPE_EMAIL, $email, $reason, $userId);
            }
            if (!empty($element->ipAddress)) {
                $block->block(BlockService::TYPE_IP, $element->ipAddress, $reason, $userId);
            }

            $element->{$element::statusAttribute()} = 'spam';
            Craft::$app->getElements()->saveElement($element);
        }

        $this->setMessage(Craft::t('stars', 'Authors blocked.'));
        return true;
    }
}
