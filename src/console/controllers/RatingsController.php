<?php

namespace justinholtweb\stars\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use justinholtweb\stars\Plugin;
use yii\console\ExitCode;

/**
 * `craft stars/ratings/...` — the stored rating summaries behind the Stars Rating field.
 */
class RatingsController extends Controller
{
    /** Rebuild only this entry's summary. */
    public ?int $entryId = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), $actionID === 'rebuild' ? ['entryId'] : []);
    }

    /**
     * Recomputes every entry's stored rating summary from its approved reviews.
     *
     * Summaries are kept current as reviews are moderated; run this after importing reviews
     * straight into the database, or restoring a backup taken mid-moderation.
     */
    public function actionRebuild(): int
    {
        $ratings = Plugin::getInstance()->ratings;

        if ($this->entryId !== null) {
            $summary = $ratings->refresh($this->entryId);
            $this->stdout(sprintf(
                "Entry %d: %s from %d approved review%s.\n",
                $this->entryId,
                $summary->count ? (string)$summary->getRounded() : '—',
                $summary->count,
                $summary->count === 1 ? '' : 's',
            ), Console::FG_GREEN);

            return ExitCode::OK;
        }

        $count = $ratings->rebuildAll();
        $this->stdout(sprintf("Rebuilt rating summaries for %d reviewed entr%s.\n", $count, $count === 1 ? 'y' : 'ies'), Console::FG_GREEN);

        return ExitCode::OK;
    }
}
