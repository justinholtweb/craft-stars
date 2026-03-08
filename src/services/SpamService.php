<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\App;
use justinholtweb\stars\Plugin;

class SpamService extends Component
{
    /**
     * Run all spam checks. Returns true if the submission appears to be spam.
     */
    public function isSpam(): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($settings->enableHoneypot && !$this->validateHoneypot()) {
            return true;
        }

        if ($settings->enableRecaptcha && !$this->validateRecaptcha()) {
            return true;
        }

        if (!$this->checkRateLimit()) {
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
     * Validate reCAPTCHA v3 token. Returns false if validation fails.
     */
    public function validateRecaptcha(): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        $token = Craft::$app->getRequest()->getBodyParam('g-recaptcha-response');

        if (empty($token)) {
            return false;
        }

        $secretKey = App::parseEnv($settings->recaptchaSecretKey);

        try {
            $client = Craft::createGuzzleClient();
            $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
                'form_params' => [
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => Craft::$app->getRequest()->getUserIP(),
                ],
            ]);

            $result = json_decode((string)$response->getBody(), true);
            return !empty($result['success']) && ($result['score'] ?? 0) >= 0.5;
        } catch (\Throwable $e) {
            Craft::error('reCAPTCHA validation error: ' . $e->getMessage(), 'stars');
            return false;
        }
    }

    /**
     * Check rate limit. Returns false if the user is submitting too frequently.
     */
    public function checkRateLimit(): bool
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
            ->from('{{%stars_reviews}}')
            ->where(['ipAddress' => $ip])
            ->andWhere(['>=', 'dateCreated', $cutoff]);

        if ($entryId) {
            $query->andWhere(['entryId' => $entryId]);
        }

        return $query->count() === 0;
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
