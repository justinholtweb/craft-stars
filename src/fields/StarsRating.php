<?php

namespace justinholtweb\stars\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\base\SortableFieldInterface;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\GqlSchema;
use GraphQL\Type\Definition\Type;
use justinholtweb\stars\fields\conditions\StarsRatingConditionRule;
use justinholtweb\stars\gql\types\RatingSummaryType;
use justinholtweb\stars\models\RatingSummary;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\RatingService;
use yii\db\Schema;

/**
 * Stars Rating — a read-only field whose value is the entry's stored rating summary (average and
 * count of approved reviews).
 *
 * Nothing is typed into it and nothing is kept in the entry's content: the value is read from
 * `{{%stars_ratings}}`, which is rewritten as reviews are moderated. What the field adds is that
 * the summary becomes part of the entry as far as Craft is concerned — an element index column,
 * a sort option, an element query param (`.rating('>= 4')`), `orderBy('rating DESC')` and a
 * condition rule ("rated 4 or more").
 */
class StarsRating extends Field implements PreviewableFieldInterface, SortableFieldInterface
{
    public static function displayName(): string
    {
        return Craft::t('stars', 'Stars Rating');
    }

    public static function icon(): string
    {
        return 'star';
    }

    public static function phpType(): string
    {
        return RatingSummary::class;
    }

    /**
     * Declared so Craft maps the handle (and `handle.count`) to SQL for `orderBy` and the element
     * index sort. The values themselves never reach the content column — serializeValue() returns
     * null — and getValueSql() points at the summary table instead.
     */
    public static function dbType(): array
    {
        return [
            'average' => Schema::TYPE_DECIMAL . '(5,2)',
            'count' => Schema::TYPE_INTEGER,
        ];
    }

    public static function isMultiInstance(): bool
    {
        return false;
    }

    public static function isRequirable(): bool
    {
        return false;
    }

    public static function supportedTranslationMethods(): array
    {
        // Reviews belong to the entry, not to one of its sites.
        return [self::TRANSLATION_METHOD_NONE];
    }

    /**
     * `.rating('>= 4')` filters by average; `.rating({average: '>= 4', count: '>= 3'})` by either
     * or both. An entry with no approved reviews has an average and count of 0.
     */
    public static function queryCondition(array $instances, mixed $value, array &$params): array|string|\yii\db\ExpressionInterface|false|null
    {
        if (is_array($value) && (array_key_exists('average', $value) || array_key_exists('count', $value))) {
            $conditions = ['and'];

            foreach (['average', 'count'] as $key) {
                if (isset($value[$key]) && $value[$key] !== '') {
                    $conditions[] = Db::parseNumericParam(RatingService::valueSql($key), $value[$key]);
                }
            }

            return count($conditions) > 1 ? $conditions : null;
        }

        return Db::parseNumericParam(RatingService::valueSql('average'), $value);
    }

    public function getValueSql(?string $key = null): ?string
    {
        return RatingService::valueSql($key ?? 'average');
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        // Whatever was passed in is ignored, a summary object included: the value is the stored
        // summary, and a copied one (a new draft gets its canonical entry's values) can be stale.
        $ratings = Plugin::getInstance()->ratings;

        if (!$element instanceof Entry || $element->id === null) {
            return new RatingSummary(['maxRating' => Plugin::getInstance()->getSettings()->maxRating]);
        }

        // Every entry in the same query result needs its summary too (an index page, a listing):
        // read them all in one go rather than one row per entry.
        if ($element->elementQueryResult !== null && count($element->elementQueryResult) > 1) {
            $ratings->preload(array_map(
                fn(ElementInterface $e) => $e->getCanonicalId(),
                array_filter($element->elementQueryResult, fn(ElementInterface $e) => $e->id !== null),
            ));
        }

        return $ratings->getSummary($element);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        return null;
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return !$value instanceof RatingSummary || $value->count === 0;
    }

    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return '';
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        return $this->_summaryHtml($value, $element);
    }

    public function getStaticHtml(mixed $value, ElementInterface $element): string
    {
        return $this->_summaryHtml($value, $element);
    }

    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!$value instanceof RatingSummary || $value->count === 0) {
            return '';
        }

        return Html::tag('span', $this->_stars($value), [
            'class' => 'stars-rating',
            'title' => Craft::t('stars', '{average} from {count, plural, =1{# review} other{# reviews}}', [
                'average' => $value->getRounded(),
                'count' => $value->count,
            ]),
        ]) . ' ' . Html::tag('span', Html::encode((string)$value->count), ['class' => 'light']);
    }

    public function previewPlaceholderHtml(mixed $value, ?ElementInterface $element): string
    {
        return $this->getPreviewHtml(new RatingSummary(['average' => 4.4, 'count' => 12]), $element ?? new Entry());
    }

    public function getElementConditionRuleType(): array|string|null
    {
        return StarsRatingConditionRule::class;
    }

    public function includeInGqlSchema(GqlSchema $schema): bool
    {
        // The summary is reviews data, so it follows the reviews permission.
        return $schema->has('stars.reviews:read');
    }

    public function getContentGqlType(): Type|array
    {
        return [
            'name' => $this->handle,
            'type' => RatingSummaryType::getType(),
            'description' => 'The entry’s stored rating summary (approved reviews only).',
        ];
    }

    private function _summaryHtml(mixed $value, ?ElementInterface $element): string
    {
        if (!$value instanceof RatingSummary || $value->count === 0) {
            return Html::tag('p', Html::encode(Craft::t('stars', 'No approved reviews yet.')), ['class' => 'light']);
        }

        $text = Craft::t('stars', '{average} out of {max} from {count, plural, =1{# approved review} other{# approved reviews}}', [
            'average' => $value->getRounded(),
            'max' => $value->maxRating,
            'count' => $value->count,
        ]);

        $html = Html::tag('span', $this->_stars($value), ['class' => 'stars-rating']) . ' ' . Html::encode($text);

        if (Craft::$app->getUser()->checkPermission('stars:viewReviews')) {
            $html .= ' · ' . Html::a(Html::encode(Craft::t('stars', 'View reviews')), UrlHelper::cpUrl('stars/reviews'));
        }

        return Html::tag('div', $html);
    }

    private function _stars(RatingSummary $summary): string
    {
        $filled = (int)round($summary->average);
        $html = '';

        for ($i = 1; $i <= $summary->maxRating; $i++) {
            $html .= $i <= $filled ? '<span class="star filled">★</span>' : '<span class="star empty">☆</span>';
        }

        return $html;
    }
}
