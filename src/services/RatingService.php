<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\Entry;
use justinholtweb\stars\models\RatingSummary;
use justinholtweb\stars\Plugin;

/**
 * Keeps one stored rating summary (average + count of approved reviews) per reviewed entry.
 *
 * The summary lives in its own table rather than in the entry's content: rewriting it is one
 * upsert, where saving the entry would cost a full element save and a new revision every time a
 * review was moderated. Review::afterSave/afterDelete/afterRestore call refresh(); `stars/ratings/rebuild`
 * recomputes the lot.
 */
class RatingService extends Component
{
    public const TABLE = '{{%stars_ratings}}';

    /**
     * Rows already read this request, keyed by element ID. `false` = no row (no approved reviews).
     *
     * @var array<int, array{average: float, count: int}|false>
     */
    private array $_rows = [];

    /**
     * Recompute and store the summary for one entry.
     */
    public function refresh(int $entryId): RatingSummary
    {
        $row = $this->_aggregateQuery()
            ->andWhere(['stars_reviews.entryId' => $entryId])
            ->one();

        $count = (int)($row['count'] ?? 0);
        $average = $count > 0 ? round((float)$row['average'], 2) : 0.0;

        $this->_write($entryId, $average, $count);
        $this->_invalidateCaches($entryId);

        return $this->_summary($entryId, $count > 0 ? ['average' => $average, 'count' => $count] : false);
    }

    /**
     * Recompute every stored summary from the reviews table. Returns the number of entries that
     * have at least one approved review.
     */
    public function rebuildAll(): int
    {
        $rows = $this->_aggregateQuery()->all();
        $seen = [];

        foreach ($rows as $row) {
            $entryId = (int)$row['entryId'];
            $seen[] = $entryId;
            $this->_write($entryId, round((float)$row['average'], 2), (int)$row['count']);
        }

        // Rows for entries whose last approved review has gone.
        $stale = (new Query())
            ->select(['elementId'])
            ->from(self::TABLE)
            ->where(['not in', 'elementId', $seen ?: [0]])
            ->column();

        if ($stale) {
            Craft::$app->getDb()->createCommand()
                ->delete(self::TABLE, ['elementId' => $stale])
                ->execute();
        }

        $this->_rows = [];

        foreach (array_merge($seen, array_map('intval', $stale)) as $entryId) {
            $this->_invalidateCaches($entryId);
        }

        return count($seen);
    }

    /**
     * The stored summary for an entry. Drafts and revisions read their canonical entry's.
     */
    public function getSummary(Entry|int $entry): RatingSummary
    {
        $elementId = $entry instanceof Entry ? $entry->getCanonicalId() : $entry;

        if ($elementId === null) {
            return $this->_summary(null, false);
        }

        if (!array_key_exists($elementId, $this->_rows)) {
            $this->preload([$elementId]);
        }

        return $this->_summary($elementId, $this->_rows[$elementId] ?? false);
    }

    /**
     * Read the summaries for many elements in one query (an element index page, a listing).
     *
     * @param int[] $elementIds
     */
    public function preload(array $elementIds): void
    {
        $missing = array_values(array_diff(array_map('intval', $elementIds), array_keys($this->_rows)));

        if (!$missing) {
            return;
        }

        foreach ($missing as $id) {
            $this->_rows[$id] = false;
        }

        $rows = (new Query())
            ->select(['elementId', 'average', 'count'])
            ->from(self::TABLE)
            ->where(['elementId' => $missing])
            ->all();

        foreach ($rows as $row) {
            $this->_rows[(int)$row['elementId']] = [
                'average' => (float)$row['average'],
                'count' => (int)$row['count'],
            ];
        }
    }

    /**
     * A scalar subquery reading one column of the stored summary for the element in the current
     * element query — so entries can be ordered and filtered by rating in SQL. An element with no
     * approved reviews reads 0.
     *
     * @param string $column `average` or `count`
     */
    public static function valueSql(string $column): string
    {
        $column = $column === 'count' ? 'count' : 'average';

        return sprintf(
            '(COALESCE((SELECT [[stars_ratings.%s]] FROM %s [[stars_ratings]] WHERE [[stars_ratings.elementId]] = COALESCE([[elements.canonicalId]], [[elements.id]])), 0))',
            $column,
            self::TABLE,
        );
    }

    /**
     * Approved, non-trashed reviews grouped by entry.
     */
    private function _aggregateQuery(): Query
    {
        return (new Query())
            ->select([
                'entryId' => 'stars_reviews.entryId',
                'average' => 'AVG([[stars_reviews.rating]])',
                'count' => 'COUNT(*)',
            ])
            ->from(['stars_reviews' => '{{%stars_reviews}}'])
            ->innerJoin(['elements' => '{{%elements}}'], '[[elements.id]] = [[stars_reviews.id]]')
            ->where([
                'stars_reviews.reviewStatus' => 'approved',
                'elements.dateDeleted' => null,
            ])
            ->andWhere(['not', ['stars_reviews.entryId' => null]])
            ->groupBy(['stars_reviews.entryId']);
    }

    private function _write(int $entryId, float $average, int $count): void
    {
        $db = Craft::$app->getDb();

        if ($count === 0) {
            $db->createCommand()->delete(self::TABLE, ['elementId' => $entryId])->execute();
            $this->_rows[$entryId] = false;
            return;
        }

        // The entry may have been hard-deleted between the review save and now; the FK would
        // refuse the row, and there is nothing to summarise anyway.
        if (!(new Query())->from('{{%elements}}')->where(['id' => $entryId])->exists()) {
            $this->_rows[$entryId] = false;
            return;
        }

        $db->createCommand()->upsert(self::TABLE, [
            'elementId' => $entryId,
            'average' => $average,
            'count' => $count,
        ], [
            'average' => $average,
            'count' => $count,
        ])->execute();

        $this->_rows[$entryId] = ['average' => $average, 'count' => $count];
    }

    /**
     * Template caches and GraphQL caches holding the entry's old rating.
     */
    private function _invalidateCaches(int $entryId): void
    {
        $entry = Entry::find()->id($entryId)->status(null)->site('*')->one();

        if ($entry !== null) {
            Craft::$app->getElements()->invalidateCachesForElement($entry);
        }
    }

    /**
     * @param array{average: float, count: int}|false $row
     */
    private function _summary(?int $elementId, array|false $row): RatingSummary
    {
        return new RatingSummary([
            'elementId' => $elementId,
            'average' => $row ? $row['average'] : 0.0,
            'count' => $row ? $row['count'] : 0,
            'maxRating' => Plugin::getInstance()->getSettings()->maxRating,
        ]);
    }
}
