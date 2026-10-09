<?php

namespace justinholtweb\stars\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use justinholtweb\stars\models\RatingSummary;

/**
 * An entry's stored rating summary: `starsRating { average count maxRating }`.
 */
class RatingSummaryType
{
    public static function getName(): string
    {
        return 'StarsRatingSummary';
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'description' => 'The average and count of an entry’s approved reviews.',
            'fields' => fn() => [
                'average' => [
                    'type' => Type::nonNull(Type::float()),
                    'description' => 'Average rating to one decimal place. 0 when there are no approved reviews.',
                    'resolve' => fn(RatingSummary $summary) => $summary->getRounded(),
                ],
                'count' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'Number of approved reviews.',
                ],
                'maxRating' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'The scale the average is out of.',
                ],
            ],
        ]));
    }
}
