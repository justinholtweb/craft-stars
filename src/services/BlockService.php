<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;

/**
 * Manages the blocklist: submitters blocked by email, IP, or user id.
 */
class BlockService extends Component
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_IP = 'ip';
    public const TYPE_USER = 'user';

    /**
     * Whether a submission from the given identifiers is blocked. Any single
     * match blocks the submission.
     */
    public function isBlocked(?string $email = null, ?string $ip = null, ?int $userId = null): bool
    {
        $conditions = ['or'];

        if (!empty($email)) {
            $conditions[] = ['and', ['type' => self::TYPE_EMAIL], ['value' => $this->_normalize(self::TYPE_EMAIL, $email)]];
        }

        if (!empty($ip)) {
            $conditions[] = ['and', ['type' => self::TYPE_IP], ['value' => $this->_normalize(self::TYPE_IP, $ip)]];
        }

        if ($userId !== null) {
            $conditions[] = ['and', ['type' => self::TYPE_USER], ['value' => (string)$userId]];
        }

        // No identifiers to check.
        if (count($conditions) === 1) {
            return false;
        }

        return (new Query())
            ->from('{{%stars_blocklist}}')
            ->where($conditions)
            ->exists();
    }

    /**
     * Add an entry to the blocklist. Idempotent on (type, value).
     */
    public function block(string $type, string $value, ?string $reason = null, ?int $createdBy = null): bool
    {
        $type = strtolower($type);
        $value = $this->_normalize($type, $value);

        if ($value === '') {
            return false;
        }

        if ($this->_exists($type, $value)) {
            return true;
        }

        $now = Db::prepareDateForDb(new \DateTime());

        Craft::$app->getDb()->createCommand()
            ->insert('{{%stars_blocklist}}', [
                'type' => $type,
                'value' => $value,
                'reason' => $reason,
                'createdBy' => $createdBy,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => \craft\helpers\StringHelper::UUID(),
            ])
            ->execute();

        return true;
    }

    /**
     * Remove an entry from the blocklist.
     */
    public function unblock(string $type, string $value): bool
    {
        $type = strtolower($type);
        $value = $this->_normalize($type, $value);

        Craft::$app->getDb()->createCommand()
            ->delete('{{%stars_blocklist}}', ['type' => $type, 'value' => $value])
            ->execute();

        return true;
    }

    /**
     * Remove a blocklist entry by its id.
     */
    public function unblockById(int $id): bool
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%stars_blocklist}}', ['id' => $id])
            ->execute();

        return true;
    }

    /**
     * All blocklist entries, newest first.
     */
    public function getAll(): array
    {
        return (new Query())
            ->from('{{%stars_blocklist}}')
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }

    private function _exists(string $type, string $value): bool
    {
        return (new Query())
            ->from('{{%stars_blocklist}}')
            ->where(['type' => $type, 'value' => $value])
            ->exists();
    }

    /**
     * Normalize a value for consistent matching (emails are lowercased/trimmed).
     */
    private function _normalize(string $type, string $value): string
    {
        $value = trim($value);
        return $type === self::TYPE_EMAIL ? mb_strtolower($value) : $value;
    }
}
