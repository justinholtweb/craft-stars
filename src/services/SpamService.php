<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\captcha\CaptchaProviderFactory;

class SpamService extends Component
{
    /**
     * Run all spam checks. Returns true if the submission appears to be spam.
     *
     * @param string $context Which submission type is being checked ('reviews'
     *                        or 'comments') — determines the rate-limit table.
     */
    public function isSpam(string $context = 'reviews'): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($settings->enableHoneypot && !$this->validateHoneypot()) {
            return true;
        }

        if (!$this->validateCaptcha()) {
            return true;
        }

        if (!$this->checkRateLimit($context)) {
            return true;
        }

        if (!$this->validateSubmissionTime()) {
            return true;
        }

        return false;
    }

    /**
     * Validate honeypot field. Returns false if the honeypot was filled (spam).
     */
    public function validateHoneypot(): bool
    {
        $honeypot = Craft::$app->getRequest()->getBodyParam('starsHoneypot');
        return empty($honeypot);
    }

    /**
     * Validate the configured captcha. Returns true when no captcha is
     * configured, or when the submitted token verifies against the provider.
     */
    public function validateCaptcha(): bool
    {
        $provider = CaptchaProviderFactory::fromSettings(Plugin::getInstance()->getSettings());

        if ($provider === null) {
            return true;
        }

        $token = (string)Craft::$app->getRequest()->getBodyParam($provider->tokenField());

        return $provider->verify($token, Craft::$app->getRequest()->getUserIP());
    }

    /**
     * @deprecated Use validateCaptcha(). Retained for backwards compatibility.
     */
    public function validateRecaptcha(): bool
    {
        return $this->validateCaptcha();
    }

    /**
     * Check rate limit. Returns false if the user is submitting too frequently.
     *
     * @param string $context 'reviews' or 'comments' — selects the table to
     *                        count recent submissions against.
     */
    public function checkRateLimit(string $context = 'reviews'): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($settings->rateLimitMinutes <= 0) {
            return true;
        }

        $ip = Craft::$app->getRequest()->getUserIP();
        $entryId = Craft::$app->getRequest()->getBodyParam('entryId');

        if (empty($ip)) {
            return true;
        }

        $cutoff = (new \DateTime())->modify("-{$settings->rateLimitMinutes} minutes")->format('Y-m-d H:i:s');

        $query = (new Query())
            ->from($this->_tableForContext($context))
            ->where(['ipAddress' => $ip])
            ->andWhere(['>=', 'dateCreated', $cutoff]);

        if ($entryId) {
            $query->andWhere(['entryId' => $entryId]);
        }

        // count() can return a numeric string; cast before comparing.
        return (int)$query->count() === 0;
    }

    /**
     * Map a submission context to its custom table.
     */
    private function _tableForContext(string $context): string
    {
        return $context === 'comments' ? '{{%stars_comments}}' : '{{%stars_reviews}}';
    }

    /**
     * Validate submission time. Returns false if the form was submitted too quickly.
     */
    public function validateSubmissionTime(): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($settings->minSubmissionTime <= 0) {
            return true;
        }

        $timestamp = Craft::$app->getRequest()->getBodyParam('__stars_ts');

        if (empty($timestamp)) {
            return true;
        }

        $elapsed = time() - (int)$timestamp;
        return $elapsed >= $settings->minSubmissionTime;
    }
}
