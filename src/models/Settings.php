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
    public int $rateLimitMinutes = 1440;
    public int $minSubmissionTime = 3;

    // Captcha
    public string $captchaProvider = 'none'; // none | recaptcha_v3 | recaptcha_v2 | hcaptcha | turnstile
    public string $captchaSiteKey = '';
    public string $captchaSecretKey = '';
    public float $recaptchaThreshold = 0.5;

    // Legacy reCAPTCHA settings (kept for backwards compatibility; superseded by
    // the captcha* settings above).
    public bool $enableRecaptcha = false;
    public string $recaptchaSiteKey = '';
    public string $recaptchaSecretKey = '';

    // Privacy
    public bool $captureIpAddress = true;
    public bool $captureUserAgent = true;
    public bool $captureReferrer = true;

    // Schema.org
    public bool $enableSchemaOrg = true;
    public string $schemaItemType = 'Product';

    // Features
    public bool $enablePros = true;
    public bool $enableCons = true;
    public bool $enableAdminResponse = true;

    // Comments
    public bool $enableComments = true;
    public bool $commentsRequireLogin = false;
    public bool $commentsAllowAnonymous = false;
    public int $maxCommentDepth = 2;

    public function defineRules(): array
    {
        return [
            [['defaultStatus'], 'in', 'range' => ['pending', 'approved']],
            [['maxRating'], 'integer', 'min' => 1, 'max' => 10],
            [['rateLimitMinutes'], 'integer', 'min' => 0],
            [['minSubmissionTime'], 'integer', 'min' => 0],
            [['maxCommentDepth'], 'integer', 'min' => 1, 'max' => 10],
            [['captchaProvider'], 'in', 'range' => ['none', 'recaptcha_v3', 'recaptcha_v2', 'hcaptcha', 'turnstile']],
            [['recaptchaThreshold'], 'number', 'min' => 0, 'max' => 1],
            [['captchaSiteKey', 'captchaSecretKey', 'recaptchaSiteKey', 'recaptchaSecretKey', 'notificationEmails', 'schemaItemType'], 'string'],
            [['requireLogin', 'allowAnonymous', 'enableNotifications', 'enableHoneypot', 'enableRecaptcha', 'enableSchemaOrg', 'enablePros', 'enableCons', 'enableAdminResponse', 'captureIpAddress', 'captureUserAgent', 'captureReferrer', 'enableComments', 'commentsRequireLogin', 'commentsAllowAnonymous'], 'boolean'],
        ];
    }
}
