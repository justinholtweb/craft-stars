<?php

namespace justinholtweb\stars\elements\base;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\helpers\Db;

/**
 * Base class for Stars' moderated, entry-attached elements (Review, Comment).
 *
 * Provides the shared machinery those types have in common:
 *  - the four-state moderation status system (pending/approved/rejected/spam)
 *  - attachment to an entry that survives the entry's deletion
 *  - captured submission metadata (IP, user agent, referrer)
 *  - a generic custom-table write in afterSave()
 *
 * Concrete types supply their own status column, table name and column map.
 */
abstract class ModeratedElement extends Element
{
    // Shared columns
    public ?int $entryId = null;
    public ?string $ipAddress = null;
    public ?string $userAgent = null;
    public ?string $submissionUrl = null;

    // Cached entry element
    private ?Entry $_entry = null;

    /**
     * The custom column holding this element's moderation status
     * (e.g. `reviewStatus`, `commentStatus`).
     */
    abstract public static function statusAttribute(): string;

    /**
     * The custom table this element persists to (e.g. `{{%stars_reviews}}`).
     */
    abstract protected function customTableName(): string;

    /**
     * Column => value map written to the custom table on save. Should NOT
     * include `id`, `dateCreated`, `dateUpdated` or `uid` — those are added
     * automatically.
     */
    abstract protected function customAttributes(): array;

    /**
     * The submitter's email address, used for blocklisting.
     */
    abstract public function getAuthorEmail(): ?string;

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            'pending' => ['label' => Craft::t('stars', 'Pending'), 'color' => 'orange'],
            'approved' => ['label' => Craft::t('stars', 'Approved'), 'color' => 'green'],
            'rejected' => ['label' => Craft::t('stars', 'Rejected'), 'color' => 'red'],
            'spam' => ['label' => Craft::t('stars', 'Spam'), 'color' => 'light'],
        ];
    }

    public function getStatus(): ?string
    {
        return $this->{static::statusAttribute()};
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function hasUris(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public function getEntry(): ?Entry
    {
        if ($this->_entry !== null) {
            return $this->_entry;
        }

        if ($this->entryId === null) {
            return null;
        }

        $this->_entry = Entry::find()->id($this->entryId)->status(null)->one();
        return $this->_entry;
    }

    public function setEntry(?Entry $entry): void
    {
        $this->_entry = $entry;
        $this->entryId = $entry?->id;
    }

    public function setAttributes($values, $safeOnly = true): void
    {
        if (array_key_exists('entryId', $values)) {
            // The entry-select field's value may arrive as an array (a plain
            // element select) or a scalar (Cp::elementSelectFieldHtml with
            // `single: true`, which is what this field uses), depending on
            // which of its two hidden inputs the request body kept.
            $entryId = $values['entryId'];
            if (is_array($entryId)) {
                $entryId = reset($entryId) ?: null;
            }
            $entryId = ($entryId !== null && $entryId !== '') ? (int)$entryId : null;

            // The field always submits a value, even when its selection never
            // changed. If that comes back empty for an existing element that
            // already has an entryId, treat it as the field being untouched
            // rather than an explicit removal — an incidental empty
            // submission here must never silently orphan the entry link.
            if ($entryId === null && $this->id !== null && $this->entryId !== null) {
                unset($values['entryId']);
            } else {
                $values['entryId'] = $entryId;
            }
        }

        parent::setAttributes($values, $safeOnly);
    }

    public function safeAttributes(): array
    {
        return array_merge(parent::safeAttributes(), [
            'entryId',
            'ipAddress',
            'userAgent',
            'submissionUrl',
            static::statusAttribute(),
        ]);
    }

    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            Craft::$app->db->createCommand()
                ->insert($this->customTableName(), array_merge($this->customAttributes(), [
                    'id' => $this->id,
                    'dateCreated' => Db::prepareDateForDb($this->dateCreated),
                    'dateUpdated' => Db::prepareDateForDb($this->dateUpdated),
                    'uid' => $this->uid,
                ]))
                ->execute();
        } else {
            Craft::$app->db->createCommand()
                ->update($this->customTableName(), $this->customAttributes(), ['id' => $this->id])
                ->execute();
        }

        parent::afterSave($isNew);
    }
}
