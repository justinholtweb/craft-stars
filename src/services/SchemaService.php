<?php

namespace justinholtweb\stars\services;

use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\Json;
use justinholtweb\stars\Plugin;

class SchemaService extends Component
{
    /**
     * Generate JSON-LD schema.org markup for an entry's reviews.
     */
    public function generateJsonLd(Entry $entry): string
    {
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $reviewService = $plugin->reviews;

        $reviews = $reviewService->getReviewsForEntry($entry);
        $avgRating = $reviewService->getAverageRating($entry);
        $count = $reviewService->getReviewCount($entry);

        if ($count === 0) {
            return '';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $settings->schemaItemType,
            'name' => $entry->title,
            'url' => $entry->getUrl(),
        ];

        // AggregateRating
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $avgRating,
            'bestRating' => $settings->maxRating,
            'worstRating' => 1,
            'reviewCount' => $count,
        ];

        // Individual reviews
        $schemaReviews = [];
        foreach ($reviews as $review) {
            $reviewSchema = [
                '@type' => 'Review',
                'author' => [
                    '@type' => 'Person',
                    'name' => $review->reviewerName,
                ],
                'datePublished' => $review->dateCreated->format('Y-m-d'),
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => $review->rating,
                    'bestRating' => $settings->maxRating,
                    'worstRating' => 1,
                ],
            ];

            if ($review->reviewText) {
                $reviewSchema['reviewBody'] = $review->reviewText;
            }

            $schemaReviews[] = $reviewSchema;
        }

        $schema['review'] = $schemaReviews;

        $json = Json::encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
