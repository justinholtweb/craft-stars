<?php

namespace justinholtweb\stars\gql\types;

use craft\gql\GqlEntityRegistry;
use craft\gql\types\DateTime as DateTimeType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use justinholtweb\stars\elements\Review;

/**
 * A published review, as a headless front end may see it.
 *
 * Deliberately not Craft's element interface: that would hand out `status`, `enabled` and the rest of
 * the element's machinery. The reviewer's email, IP, user agent and referrer are never exposed.
 */
class ReviewType
{
    public static function getName(): string
    {
        return 'StarsReview';
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'description' => 'An approved review.',
            'fields' => fn() => [
                'id' => ['type' => Type::nonNull(Type::int())],
                'uid' => ['type' => Type::nonNull(Type::string())],
                'entryId' => [
                    'type' => Type::int(),
                    'description' => 'The ID of the entry being reviewed.',
                ],
                'rating' => ['type' => Type::nonNull(Type::int())],
                'reviewText' => ['type' => Type::string()],
                'reviewerName' => ['type' => Type::nonNull(Type::string())],
                'pros' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                    'resolve' => fn(Review $review) => array_values(array_filter($review->getProsArray(), 'is_string')),
                ],
                'cons' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                    'resolve' => fn(Review $review) => array_values(array_filter($review->getConsArray(), 'is_string')),
                ],
                'adminResponse' => ['type' => Type::string()],
                'adminResponseDate' => [
                    'type' => DateTimeType::getType(),
                    'resolve' => fn(Review $review) => $review->adminResponseDate
                        ? new \DateTime($review->adminResponseDate, new \DateTimeZone('UTC'))
                        : null,
                ],
                'dateCreated' => ['type' => DateTimeType::getType()],
                'dateUpdated' => ['type' => DateTimeType::getType()],
            ],
        ]));
    }
}
