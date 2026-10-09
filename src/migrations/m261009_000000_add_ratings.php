<?php

namespace justinholtweb\stars\migrations;

use craft\db\Migration;
use justinholtweb\stars\services\RatingService;

/**
 * Adds {{%stars_ratings}} — one stored rating summary per reviewed entry — and fills it from the
 * reviews already approved. Fresh installs get the table from Install::safeUp() instead.
 */
class m261009_000000_add_ratings extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(RatingService::TABLE)) {
            Install::createRatingsTable($this);
        }

        // Plain SQL rather than element queries: migrations run before the plugin's settings and
        // element types are guaranteed to be in a usable state.
        $rows = (new \craft\db\Query())
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
            ->groupBy(['stars_reviews.entryId'])
            ->all($this->db);

        foreach ($rows as $row) {
            $this->upsert(RatingService::TABLE, [
                'elementId' => (int)$row['entryId'],
                'average' => round((float)$row['average'], 2),
                'count' => (int)$row['count'],
            ], [
                'average' => round((float)$row['average'], 2),
                'count' => (int)$row['count'],
            ]);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(RatingService::TABLE);
        return true;
    }
}
