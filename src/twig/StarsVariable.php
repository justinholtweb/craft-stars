<?php

namespace justinholtweb\stars\twig;

use craft\elements\Entry;
use justinholtweb\stars\elements\db\ReviewQuery;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\captcha\CaptchaProviderFactory;

class StarsVariable
{
    /**
     * Returns a ReviewQuery for approved reviews of an entry, ordered by date DESC.
     */
    public function forEntry(Entry|int $entry): ReviewQuery
    {
        $entryId = $entry instanceof Entry ? $entry->id : $entry;

        return Review::find()
            ->entryId($entryId)
            ->reviewStatus('approved')
            ->orderBy(['dateCreated' => SORT_DESC]);
    }

    /**
     * Get average rating for an entry.
     */
    public function averageRating(Entry|int $entry): float
    {
        return Plugin::getInstance()->reviews->getAverageRating($entry);
    }

    /**
     * Get count of approved reviews for an entry.
     */
    public function count(Entry|int $entry): int
    {
        return Plugin::getInstance()->reviews->getReviewCount($entry);
    }

    /**
     * Get rating distribution for an entry.
     */
    public function distribution(Entry|int $entry): array
    {
        return Plugin::getInstance()->reviews->getRatingDistribution($entry);
    }

    /**
     * Generate JSON-LD schema.org markup for an entry.
     */
    public function schemaOrg(Entry $entry): string
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableSchemaOrg) {
            return '';
        }

        return Plugin::getInstance()->schema->generateJsonLd($entry);
    }

    /**
     * The active captcha provider + site key for rendering the widget, or null.
     *
     * @return array{provider: string, siteKey: string}|null
     */
    public function captcha(): ?array
    {
        return CaptchaProviderFactory::frontendConfig(Plugin::getInstance()->getSettings());
    }
}
