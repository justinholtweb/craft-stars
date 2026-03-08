<?php

namespace justinholtweb\stars\models;

use craft\base\Model;

class Settings extends Model
{
    // Moderation
    public string $defaultStatus = 'pending';
    public bool $requireLogin = false;
    public bool $allowAnonymous = false;

    // Rating
    public int $maxRating = 5;

    // Notifications
    public bool $enableNotifications = true;
    public string $notificationEmails = '';

    // Anti-Spam
    public bool $enableHoneypot = true;
    public bool $enableRecaptcha = false;
    public string $recaptchaSiteKey = '';
    public string $recaptchaSecretKey = '';
    public int $rateLimitMinutes = 1440;
    public int $minSubmissionTime = 3;

    // Schema.org
    public bool $enableSchemaOrg = true;
    public string $schemaItemType = 'Product';

    // Features
    public bool $enablePros = true;
    public bool $enableCons = true;
    public bool $enableAdminResponse = true;

    public function defineRules(): array
    {
        return [
            [['defaultStatus'], 'in', 'range' => ['pending', 'approved']],
            [['maxRating'], 'integer', 'min' => 1, 'max' => 10],
            [['rateLimitMinutes'], 'integer', 'min' => 0],
            [['minSubmissionTime'], 'integer', 'min' => 0],
            [['recaptchaSiteKey', 'recaptchaSecretKey', 'notificationEmails', 'schemaItemType'], 'string'],
            [['requireLogin', 'allowAnonymous', 'enableNotifications', 'enableHoneypot', 'enableRecaptcha', 'enableSchemaOrg', 'enablePros', 'enableCons', 'enableAdminResponse'], 'boolean'],
        ];
    }
}
