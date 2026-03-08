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

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%stars_reviews}}');
        return true;
    }
}
