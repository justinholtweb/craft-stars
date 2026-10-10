<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\captcha\CaptchaProviderFactory;

class SpamService extends Component
{
    public const FAILURE_HONEYPOT = 'honeypot';
    public const FAILURE_CAPTCHA = 'captcha';
    public const FAILURE_RATE_LIMIT = 'rateLimit';
    public const FAILURE_SUBMISSION_TIME = 'submissionTime';

    /**
     * Run all spam checks. Returns true if the submission appears to be spam.
     *
     * @param string $context Which submission type is being checked ('reviews'
     *                        or 'comments') — determines the rate-limit table.
     */
    public function isSpam(string $context = 'reviews'): bool
    {
        return $this->check($context) !== null;
    }

    /**
     * Run all spam checks and report the first one that failed, as one of the
     * FAILURE_* constants, or null when the submission passes. Lets callers
     * tell a person who posted recently apart from a bot.
     *
     * @param string $context Which submission type is being checked ('reviews'
     *                        or 'comments') — determines the rate-limit table.
     */
    public function check(string $context = 'reviews'): ?string
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($settings->enableHoneypot && !$this->validateHoneypot()) {
            return self::FAILURE_HONEYPOT;
        }

        if (!$this->validateCaptcha()) {
            return self::FAILURE_CAPTCHA;
        }

        if (!$this->checkRateLimit($context) || !$this->checkAddressBudget()) {
            return self::FAILURE_RATE_LIMIT;
        }

        if (!$this->validateSubmissionTime()) {
            return self::FAILURE_SUBMISSION_TIME;
        }

        return null;
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

        // dateCreated is stored in UTC, so the cutoff has to be too.
        $cutoff = Db::prepareDateForDb(new \DateTime("-{$settings->rateLimitMinutes} minutes"));

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
     * The hourly budget for one address, across every entry and both submission types. Returns
     * false once it is spent.
     *
     * checkRateLimit() is per entry and reads the IP stored on each row, so it stops nothing when IP
     * capture is off, and a script could post to every entry on the site in turn. This one is kept
     * in the cache, so it holds whatever the privacy settings are, and it counts the attempt — a
     * submission that is later refused still spends from it.
     */
    public function checkAddressBudget(): bool
    {
        $limit = Plugin::getInstance()->getSettings()->maxSubmissionsPerHour;

        if ($limit <= 0) {
            return true;
        }

        $key = sprintf('stars:budget:%s:%d', sha1(self::address()), intdiv(time(), 3600));
        $mutex = Craft::$app->getMutex();

        // Under a lock, or parallel requests all read the same count. A busy lock refuses.
        if (!$mutex->acquire($key, 2)) {
            return false;
        }

        try {
            $cache = Craft::$app->getCache();
            $count = (int)$cache->get($key);

            if ($count >= $limit) {
                return false;
            }

            $cache->set($key, $count + 1, 3700);

            return true;
        } finally {
            $mutex->release($key);
        }
    }

    /**
     * The address a submission is charged to.
     *
     * Craft trusts every host by default (`trustedHosts` is `['any']`), which makes
     * `X-Forwarded-For` something any client sets — a new value per request and a budget never
     * runs out. So the forwarded address is believed only once a site has said which proxies to
     * trust. An IPv6 address is charged by its /64, since one machine is routinely given a whole one.
     */
    public static function address(): string
    {
        $request = Craft::$app->getRequest();
        $trusted = Craft::$app->getConfig()->getGeneral()->trustedHosts;
        $ip = (string)($trusted === ['any'] ? $request->getRemoteIP() : $request->getUserIP());

        if (str_contains($ip, ':') && ($packed = @inet_pton($ip)) !== false) {
            return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
        }

        return $ip;
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
