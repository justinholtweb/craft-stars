<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\events\TemplateEvent;
use craft\helpers\Json;
use craft\web\View;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\events\DefineSchemaEvent;
use justinholtweb\stars\Plugin;

class SchemaService extends Component
{
    /**
     * @event DefineSchemaEvent Before an entry's JSON-LD is encoded. Set `$event->schema` to null to
     * print nothing.
     */
    public const EVENT_DEFINE_SCHEMA = 'defineSchema';

    /**
     * Organisation types, LocalBusiness and its common subtypes. Google does not show review stars
     * for these when the reviews are about the business publishing them ("self-serving" reviews),
     * and treats marking them up as a guideline violation — so Stars only marks them up when the
     * site says its reviews are about *other* businesses (a directory, a guide).
     */
    public const SELF_SERVING_TYPES = [
        'Organization', 'Corporation', 'EducationalOrganization', 'NGO',
        'LocalBusiness', 'Restaurant', 'FoodEstablishment', 'Store', 'Hotel', 'LodgingBusiness',
        'ProfessionalService', 'MedicalBusiness', 'Dentist', 'AutomotiveBusiness',
        'HomeAndConstructionBusiness', 'HealthAndBeautyBusiness', 'LegalService', 'FinancialService',
        'EntertainmentBusiness', 'SportsActivityLocation',
    ];

    /**
     * Entry IDs whose JSON-LD has already been printed this request, so automatic injection never
     * prints a second copy next to a template's own `schemaOrg()` call.
     *
     * @var array<int, true>
     */
    private array $_rendered = [];

    /**
     * Generate JSON-LD schema.org markup for an entry's reviews.
     *
     * @param array{type?: string, limit?: int} $options `type` overrides the Schema Item Type
     * setting; `limit` the Schema Review Limit setting.
     */
    public function generateJsonLd(Entry $entry, array $options = []): string
    {
        $schema = $this->getSchema($entry, $options);

        if ($schema === null) {
            return '';
        }

        $this->_rendered[(int)$entry->getCanonicalId()] = true;

        $json = Json::encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * The JSON-LD object for an entry, or null when there is nothing Google would accept: no
     * approved reviews, or a self-serving organisation type.
     *
     * @param array{type?: string, limit?: int} $options
     */
    public function getSchema(Entry $entry, array $options = []): ?array
    {
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        $type = trim((string)($options['type'] ?? $settings->schemaItemType)) ?: 'Product';
        $limit = max(0, (int)($options['limit'] ?? $settings->schemaReviewLimit));

        $summary = $plugin->ratings->getSummary($entry);
        $schema = null;

        if ($summary->count > 0 && ($settings->schemaThirdPartyReviews || !$this->isSelfServingType($type))) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => $type,
                'name' => (string)$entry->title,
            ];

            if ($url = $entry->getUrl()) {
                $schema['url'] = $url;
            }

            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $summary->getRounded(),
                'bestRating' => $summary->maxRating,
                'worstRating' => 1,
                'reviewCount' => $summary->count,
            ];

            $schemaReviews = $limit > 0 ? $this->_reviews($entry, $limit, $summary->maxRating) : [];

            if ($schemaReviews) {
                $schema['review'] = $schemaReviews;
            }
        }

        if ($this->hasEventHandlers(self::EVENT_DEFINE_SCHEMA)) {
            $event = new DefineSchemaEvent([
                'entry' => $entry,
                'schema' => $schema,
                'options' => $options,
            ]);
            $this->trigger(self::EVENT_DEFINE_SCHEMA, $event);
            $schema = $event->schema;
        }

        return $schema;
    }

    public function isSelfServingType(string $type): bool
    {
        return in_array($type, self::SELF_SERVING_TYPES, true);
    }

    public function hasRendered(Entry $entry): bool
    {
        return isset($this->_rendered[(int)$entry->getCanonicalId()]);
    }

    /**
     * View::EVENT_AFTER_RENDER_PAGE_TEMPLATE handler for the Inject Schema.org setting: adds the
     * JSON-LD for the page's own entry just before `</head>`. Only on site requests, only for the
     * entry the URL matched, and never twice.
     */
    public function injectIntoPage(TemplateEvent $event): void
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableSchemaOrg || !$settings->injectSchemaOrg || $event->templateMode !== View::TEMPLATE_MODE_SITE) {
            return;
        }

        $request = Craft::$app->getRequest();

        if (!$request->getIsSiteRequest() || $request->getIsActionRequest()) {
            return;
        }

        $entry = Craft::$app->getUrlManager()->getMatchedElement();

        if (!$entry instanceof Entry || $this->hasRendered($entry)) {
            return;
        }

        $position = stripos($event->output, '</head>');

        if ($position === false) {
            return;
        }

        try {
            $tag = $this->generateJsonLd($entry);
        } catch (\Throwable $e) {
            // Structured data is never worth taking a page down for.
            Craft::warning('Stars could not build JSON-LD for entry ' . $entry->id . ': ' . $e->getMessage(), __METHOD__);
            return;
        }

        if ($tag !== '') {
            $event->output = substr($event->output, 0, $position) . $tag . "\n" . substr($event->output, $position);
        }
    }

    /**
     * The most recent approved reviews, as schema.org Review objects. Reviews without a name are
     * left out — Google requires an author name — but still count towards the aggregate.
     */
    private function _reviews(Entry $entry, int $limit, int $maxRating): array
    {
        $reviews = Review::find()
            ->entryId($entry->getCanonicalId())
            ->reviewStatus('approved')
            ->andWhere(['not', ['stars_reviews.reviewerName' => '']])
            ->orderBy(['elements.dateCreated' => SORT_DESC, 'elements.id' => SORT_DESC])
            ->limit($limit)
            ->all();

        $schemaReviews = [];

        foreach ($reviews as $review) {
            /** @var Review $review */
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
                    'bestRating' => $maxRating,
                    'worstRating' => 1,
                ],
            ];

            if ($review->reviewText) {
                $reviewSchema['reviewBody'] = $review->reviewText;
            }

            $schemaReviews[] = $reviewSchema;
        }

        return $schemaReviews;
    }
}
