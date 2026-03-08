<?php

namespace justinholtweb\stars\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $entryId
 * @property int $rating
 * @property string|null $reviewText
 * @property string $reviewerName
 * @property string|null $reviewerEmail
 * @property string|null $pros
 * @property string|null $cons
 * @property string|null $adminResponse
 * @property string|null $adminResponseDate
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property string|null $submissionUrl
 * @property string $reviewStatus
 */
class ReviewRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%stars_reviews}}';
    }
}
