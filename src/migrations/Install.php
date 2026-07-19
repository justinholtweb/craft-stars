<?php

namespace justinholtweb\stars\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%stars_reviews}}', [
            'id' => $this->primaryKey(),
            'entryId' => $this->integer(),
            'rating' => $this->tinyInteger()->notNull()->defaultValue(5),
            'reviewText' => $this->text(),
            'reviewerName' => $this->string(255)->notNull(),
            'reviewerEmail' => $this->string(255),
            'pros' => $this->text(),
            'cons' => $this->text(),
            'adminResponse' => $this->text(),
            'adminResponseDate' => $this->dateTime(),
            'ipAddress' => $this->string(45),
            'userAgent' => $this->string(512),
            'submissionUrl' => $this->string(2048),
            'reviewStatus' => $this->string(20)->notNull()->defaultValue('pending'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Foreign keys
        $this->addForeignKey(
            null,
            '{{%stars_reviews}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        $this->addForeignKey(
            null,
            '{{%stars_reviews}}',
            'entryId',
            '{{%elements}}',
            'id',
            'SET NULL',
            null
        );

        // Indexes
        $this->createIndex(null, '{{%stars_reviews}}', 'entryId');
        $this->createIndex(null, '{{%stars_reviews}}', 'rating');
        $this->createIndex(null, '{{%stars_reviews}}', 'reviewerEmail');
        $this->createIndex(null, '{{%stars_reviews}}', 'reviewStatus');
        $this->createIndex(null, '{{%stars_reviews}}', 'ipAddress');
        $this->createIndex(null, '{{%stars_reviews}}', ['entryId', 'reviewStatus']);

        $this->createTable('{{%stars_comments}}', [
            'id' => $this->primaryKey(),
            'entryId' => $this->integer(),
            'parentId' => $this->integer(),
            'authorUserId' => $this->integer(),
            'authorName' => $this->string(255)->notNull(),
            'authorEmail' => $this->string(255),
            'body' => $this->text()->notNull(),
            'commentStatus' => $this->string(20)->notNull()->defaultValue('pending'),
            'ipAddress' => $this->string(45),
            'userAgent' => $this->string(512),
            'submissionUrl' => $this->string(2048),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Foreign keys
        $this->addForeignKey(null, '{{%stars_comments}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%stars_comments}}', 'entryId', '{{%elements}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%stars_comments}}', 'authorUserId', '{{%elements}}', 'id', 'SET NULL', null);
        // parentId promotes replies to top-level when a parent is deleted (non-destructive).
        $this->addForeignKey(null, '{{%stars_comments}}', 'parentId', '{{%stars_comments}}', 'id', 'SET NULL', null);

        // Indexes
        $this->createIndex(null, '{{%stars_comments}}', 'entryId');
        $this->createIndex(null, '{{%stars_comments}}', 'parentId');
        $this->createIndex(null, '{{%stars_comments}}', 'commentStatus');
        $this->createIndex(null, '{{%stars_comments}}', ['entryId', 'commentStatus']);

        $this->createTable('{{%stars_blocklist}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string(10)->notNull(),
            'value' => $this->string(255)->notNull(),
            'reason' => $this->string(255),
            'createdBy' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, '{{%stars_blocklist}}', 'createdBy', '{{%elements}}', 'id', 'SET NULL', null);
        $this->createIndex(null, '{{%stars_blocklist}}', ['type', 'value'], true);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%stars_blocklist}}');
        $this->dropTableIfExists('{{%stars_comments}}');
        $this->dropTableIfExists('{{%stars_reviews}}');
        return true;
    }
}
