<?php

namespace justinholtweb\stars\gql\types;

use craft\gql\GqlEntityRegistry;
use craft\gql\types\DateTime as DateTimeType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * A published comment. Threads are flat here: build the tree from `parentId`, as
 * CommentService::getCommentTree() does. The author's email, user ID, IP, user agent and referrer
 * are never exposed.
 */
class CommentType
{
    public static function getName(): string
    {
        return 'StarsComment';
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new ObjectType([
            'name' => self::getName(),
            'description' => 'An approved comment.',
            'fields' => fn() => [
                'id' => ['type' => Type::nonNull(Type::int())],
                'uid' => ['type' => Type::nonNull(Type::string())],
                'entryId' => [
                    'type' => Type::int(),
                    'description' => 'The ID of the entry the comment is on.',
                ],
                'parentId' => [
                    'type' => Type::int(),
                    'description' => 'The comment this one replies to, or null for a top-level comment.',
                ],
                'authorName' => ['type' => Type::nonNull(Type::string())],
                'body' => ['type' => Type::string()],
                'dateCreated' => ['type' => DateTimeType::getType()],
                'dateUpdated' => ['type' => DateTimeType::getType()],
            ],
        ]));
    }
}
