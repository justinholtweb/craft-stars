<?php

namespace justinholtweb\stars\migrations;

use craft\db\Migration;

/**
 * Adds the {{%stars_blocklist}} table for existing installs.
 * Fresh installs get this table from Install::safeUp() instead.
 */
class m260719_010000_add_blocklist extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%stars_blocklist}}')) {
            return true;
        }

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
        return true;
    }
}
