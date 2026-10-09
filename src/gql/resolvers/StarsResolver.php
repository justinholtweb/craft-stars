<?php

namespace justinholtweb\stars\gql\resolvers;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\helpers\Gql as GqlHelper;
use justinholtweb\stars\elements\base\ModeratedQuery;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\gql\queries\StarsQueries;

/**
 * Turns GraphQL arguments into review and comment queries, and holds the schema's boundaries.
 */
class StarsResolver
{
    public static function resolveReviews(mixed $source, array $arguments): array
    {
        $query = self::reviewQuery($arguments);

        return $query === null ? [] : self::page($query, $arguments, ['elements.dateCreated' => SORT_DESC, 'elements.id' => SORT_DESC])->all();
    }

    public static function resolveReviewCount(mixed $source, array $arguments): int
    {
        return (int)(self::reviewQuery($arguments)?->count() ?? 0);
    }

    public static function resolveComments(mixed $source, array $arguments): array
    {
        $query = self::commentQuery($arguments);

        return $query === null ? [] : self::page($query, $arguments, ['elements.dateCreated' => SORT_ASC, 'elements.id' => SORT_ASC])->all();
    }

    public static function resolveCommentCount(mixed $source, array $arguments): int
    {
        return (int)(self::commentQuery($arguments)?->count() ?? 0);
    }

    /**
     * Null when the schema can't read reviews, or nothing it may read has any.
     */
    private static function reviewQuery(array $arguments): ?ModeratedQuery
    {
        if (!GqlHelper::canSchema('stars.reviews')) {
            return null;
        }

        $query = Review::find()->status('approved')->reviewStatus('approved');

        if (isset($arguments['rating'])) {
            $query->andWhere(['stars_reviews.rating' => array_map('intval', (array)$arguments['rating'])]);
        }

        if (isset($arguments['minRating'])) {
            $query->minRating((int)$arguments['minRating']);
        }

        if (isset($arguments['maxRating'])) {
            $query->maxRating((int)$arguments['maxRating']);
        }

        return self::restrictToReadableEntries($query, '{{%stars_reviews}}', 'reviewStatus', $arguments['entryId'] ?? null);
    }

    private static function commentQuery(array $arguments): ?ModeratedQuery
    {
        if (!GqlHelper::canSchema('stars.comments')) {
            return null;
        }

        $query = Comment::find()->status('approved')->commentStatus('approved');

        if (isset($arguments['parentId'])) {
            $query->parentId((int)$arguments['parentId']);
        }

        if (!empty($arguments['topLevel'])) {
            $query->topLevel();
        }

        return self::restrictToReadableEntries($query, '{{%stars_comments}}', 'commentStatus', $arguments['entryId'] ?? null);
    }

    /**
     * Keep only rows whose entry the schema could fetch itself: live, and in a section it may read.
     *
     * Without this a token granted reviews but not, say, an unreleased product's section could read
     * that product's reviews — or the reviews of an entry that is disabled or not yet posted.
     *
     * @param int[]|null $entryIds
     */
    private static function restrictToReadableEntries(ModeratedQuery $query, string $table, string $statusColumn, ?array $entryIds): ?ModeratedQuery
    {
        $sectionIds = self::readableSectionIds();

        if ($sectionIds === []) {
            return null;
        }

        // The entries that have something approved to show — usually far fewer than the entries on
        // the site — then which of those the schema may see.
        $candidates = (new Query())
            ->select(['entryId'])
            ->distinct()
            ->from($table)
            ->where([$statusColumn => 'approved'])
            ->andWhere(['not', ['entryId' => null]]);

        if ($entryIds !== null) {
            $candidates->andWhere(['entryId' => array_map('intval', $entryIds)]);
        }

        $candidateIds = array_map('intval', $candidates->column());

        if ($candidateIds === []) {
            return null;
        }

        $readable = Entry::find()
            ->id($candidateIds)
            ->sectionId($sectionIds)
            ->status('live')
            ->site('*')
            ->unique()
            ->ids();

        if ($readable === []) {
            return null;
        }

        $alias = $table === '{{%stars_reviews}}' ? 'stars_reviews' : 'stars_comments';
        $query->andWhere(["$alias.entryId" => array_map('intval', $readable)]);

        return $query;
    }

    /**
     * @return int[]
     */
    private static function readableSectionIds(): array
    {
        $uids = GqlHelper::extractAllowedEntitiesFromSchema('read')['sections'] ?? [];
        $ids = [];

        foreach ($uids as $uid) {
            $section = Craft::$app->getEntries()->getSectionByUid($uid);

            if ($section !== null) {
                $ids[] = $section->id;
            }
        }

        return $ids;
    }

    private static function page(ModeratedQuery $query, array $arguments, array $defaultOrder): ModeratedQuery
    {
        $limit = (int)($arguments['limit'] ?? StarsQueries::MAX_LIMIT);
        $limit = max(1, min($limit, StarsQueries::MAX_LIMIT));

        return $query
            ->orderBy($arguments['orderBy'] ?? $defaultOrder)
            ->limit($limit)
            ->offset(max(0, (int)($arguments['offset'] ?? 0)));
    }
}
