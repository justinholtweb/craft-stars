<?php

namespace justinholtweb\stars\migrations;

use craft\db\Migration;

/**
 * Adds the {{%stars_comments}} table for existing installs upgrading to 6.0.0.
 * Fresh installs get this table from Install::safeUp() instead.
 */
class m260719_000000_add_comments extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%stars_comments}}')) {
            return true;
        }

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

        $this->addForeignKey(null, '{{%stars_comments}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%stars_comments}}', 'entryId', '{{%elements}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%stars_comments}}', 'authorUserId', '{{%elements}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%stars_comments}}', 'parentId', '{{%stars_comments}}', 'id', 'SET NULL', null);

        $this->createIndex(null, '{{%stars_comments}}', 'entryId');
        $this->createIndex(null, '{{%stars_comments}}', 'parentId');
        $this->createIndex(null, '{{%stars_comments}}', 'commentStatus');
        $this->createIndex(null, '{{%stars_comments}}', ['entryId', 'commentStatus']);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%stars_comments}}');
        return true;
    }
}
