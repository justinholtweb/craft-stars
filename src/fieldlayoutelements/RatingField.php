<?php

namespace justinholtweb\stars\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use justinholtweb\stars\Plugin;

/**
 * The star rating selector, rendered in the Review editor's main body.
 *
 * The option list is built from the `maxRating` setting, so a site using a
 * 10-star scale gets ten options without any further configuration.
 */
class RatingField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public string $attribute = 'rating';

    /**
     * @inheritdoc
     */
    public bool $required = true;

    /**
     * @inheritdoc
     */
    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('stars', 'Rating');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        $maxRating = Plugin::getInstance()->getSettings()->maxRating;

        $options = [];
        for ($i = 1; $i <= $maxRating; $i++) {
            $options[] = [
                'label' => str_repeat('★', $i) . str_repeat('☆', $maxRating - $i) . " ($i)",
                'value' => $i,
            ];
        }

        return Cp::selectHtml([
            'id' => $this->id(),
            'describedBy' => $this->describedBy($element, $static),
            'name' => $this->attribute,
            'value' => $this->value($element),
            'options' => $options,
            'disabled' => $static,
        ]);
    }
}
