<?php

namespace justinholtweb\stars\gql\queries;

use craft\gql\base\Query;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;
use justinholtweb\stars\gql\resolvers\StarsResolver;
use justinholtweb\stars\gql\types\CommentType;
use justinholtweb\stars\gql\types\ReviewType;

/**
 * Stars' root queries — read-only, approved content only.
 *
 * Each half is behind its own schema component (`stars.reviews:read`, `stars.comments:read`), and on
 * top of that a review or comment is only returned when its entry is live and in a section the
 * schema may read: the same entry a token could fetch itself.
 */
class StarsQueries extends Query
{
    public const MAX_LIMIT = 100;

    public static function getQueries(bool $checkToken = true): array
    {
        $queries = [];

        if (!$checkToken || GqlHelper::canSchema('stars.reviews')) {
            $reviewArgs = self::reviewArguments();

            $queries['starsReviews'] = [
                'type' => Type::nonNull(Type::listOf(Type::nonNull(ReviewType::getType()))),
                'args' => $reviewArgs + self::pageArguments(self::reviewOrder()),
                'resolve' => StarsResolver::class . '::resolveReviews',
                'description' => 'Approved reviews, newest first unless ordered otherwise.',
            ];
            $queries['starsReviewCount'] = [
                'type' => Type::nonNull(Type::int()),
                'args' => $reviewArgs,
                'resolve' => StarsResolver::class . '::resolveReviewCount',
                'description' => 'How many approved reviews match.',
            ];
        }

        if (!$checkToken || GqlHelper::canSchema('stars.comments')) {
            $commentArgs = self::commentArguments();

            $queries['starsComments'] = [
                'type' => Type::nonNull(Type::listOf(Type::nonNull(CommentType::getType()))),
                'args' => $commentArgs + self::pageArguments(self::commentOrder()),
                'resolve' => StarsResolver::class . '::resolveComments',
                'description' => 'Approved comments, oldest first unless ordered otherwise.',
            ];
            $queries['starsCommentCount'] = [
                'type' => Type::nonNull(Type::int()),
                'args' => $commentArgs,
                'resolve' => StarsResolver::class . '::resolveCommentCount',
                'description' => 'How many approved comments match.',
            ];
        }

        return $queries;
    }

    private static function reviewArguments(): array
    {
        return [
            'entryId' => [
                'type' => Type::listOf(Type::int()),
                'description' => 'Narrow to reviews of these entries.',
            ],
            'rating' => [
                'type' => Type::listOf(Type::int()),
                'description' => 'Narrow to these exact ratings.',
            ],
            'minRating' => ['type' => Type::int()],
            'maxRating' => ['type' => Type::int()],
        ];
    }

    private static function commentArguments(): array
    {
        return [
            'entryId' => [
                'type' => Type::listOf(Type::int()),
                'description' => 'Narrow to comments on these entries.',
            ],
            'parentId' => [
                'type' => Type::int(),
                'description' => 'Narrow to replies to this comment.',
            ],
            'topLevel' => [
                'type' => Type::boolean(),
                'description' => 'Only comments that are not replies.',
            ],
        ];
    }

    private static function pageArguments(EnumType $order): array
    {
        return [
            'limit' => [
                'type' => Type::int(),
                'description' => 'At most ' . self::MAX_LIMIT . ', which is also the default.',
            ],
            'offset' => ['type' => Type::int()],
            'orderBy' => ['type' => $order],
        ];
    }

    /**
     * An enum rather than a free string, so nothing a client sends ends up in ORDER BY.
     */
    private static function reviewOrder(): EnumType
    {
        return GqlEntityRegistry::getOrCreate('StarsReviewOrder', fn() => new EnumType([
            'name' => 'StarsReviewOrder',
            'values' => [
                'NEWEST' => ['value' => ['elements.dateCreated' => SORT_DESC, 'elements.id' => SORT_DESC]],
                'OLDEST' => ['value' => ['elements.dateCreated' => SORT_ASC, 'elements.id' => SORT_ASC]],
                'HIGHEST' => ['value' => ['stars_reviews.rating' => SORT_DESC, 'elements.dateCreated' => SORT_DESC]],
                'LOWEST' => ['value' => ['stars_reviews.rating' => SORT_ASC, 'elements.dateCreated' => SORT_DESC]],
            ],
        ]));
    }

    private static function commentOrder(): EnumType
    {
        return GqlEntityRegistry::getOrCreate('StarsCommentOrder', fn() => new EnumType([
            'name' => 'StarsCommentOrder',
            'values' => [
                'OLDEST' => ['value' => ['elements.dateCreated' => SORT_ASC, 'elements.id' => SORT_ASC]],
                'NEWEST' => ['value' => ['elements.dateCreated' => SORT_DESC, 'elements.id' => SORT_DESC]],
            ],
        ]));
    }
}
