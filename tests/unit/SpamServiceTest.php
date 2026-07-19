<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Stub;
use Codeception\Test\Unit;
use Craft;
use craft\web\Request;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\SpamService;

/**
 * Covers the individual spam checks. reCAPTCHA (which makes an HTTP call) is
 * left to integration testing; the honeypot, submission-time and rate-limit
 * checks are all exercised here against a stubbed request.
 */
class SpamServiceTest extends Unit
{
    use CreatesReviews;

    protected \UnitTester $tester;

    private SpamService $spam;

    protected function _before(): void
    {
        $this->spam = Plugin::getInstance()->spam;
    }

    /**
     * Swap in a fake web request exposing the given body params + IP.
     */
    private function fakeRequest(array $bodyParams = [], ?string $ip = '10.0.0.1'): void
    {
        $request = Stub::makeEmpty(Request::class, [
            'getBodyParam' => fn($name, $defaultValue = null) => $bodyParams[$name] ?? $defaultValue,
            'getUserIP' => $ip,
        ]);

        Craft::$app->set('request', $request);
    }

    public function testHoneypotPassesWhenEmpty(): void
    {
        $this->fakeRequest(['starsHoneypot' => '']);
        self::assertTrue($this->spam->validateHoneypot());
    }

    public function testHoneypotFailsWhenFilled(): void
    {
        $this->fakeRequest(['starsHoneypot' => 'i-am-a-bot']);
        self::assertFalse($this->spam->validateHoneypot());
    }

    public function testSubmissionTimeFailsWhenTooFast(): void
    {
        Plugin::getInstance()->getSettings()->minSubmissionTime = 3;
        $this->fakeRequest(['__stars_ts' => time()]);
        self::assertFalse($this->spam->validateSubmissionTime());
    }

    public function testSubmissionTimePassesAfterEnoughTime(): void
    {
        Plugin::getInstance()->getSettings()->minSubmissionTime = 3;
        $this->fakeRequest(['__stars_ts' => time() - 10]);
        self::assertTrue($this->spam->validateSubmissionTime());
    }

    public function testSubmissionTimeDisabledAlwaysPasses(): void
    {
        Plugin::getInstance()->getSettings()->minSubmissionTime = 0;
        $this->fakeRequest(['__stars_ts' => time()]);
        self::assertTrue($this->spam->validateSubmissionTime());
    }

    public function testRateLimitBlocksRepeatIp(): void
    {
        Plugin::getInstance()->getSettings()->rateLimitMinutes = 1440;
        $this->createReview(['ipAddress' => '203.0.113.7']);

        $this->fakeRequest([], '203.0.113.7');
        self::assertFalse($this->spam->checkRateLimit());
    }

    public function testRateLimitAllowsFreshIp(): void
    {
        Plugin::getInstance()->getSettings()->rateLimitMinutes = 1440;

        $this->fakeRequest([], '198.51.100.42');
        self::assertTrue($this->spam->checkRateLimit());
    }

    public function testRateLimitDisabledAlwaysAllows(): void
    {
        Plugin::getInstance()->getSettings()->rateLimitMinutes = 0;
        $this->createReview(['ipAddress' => '203.0.113.99']);

        $this->fakeRequest([], '203.0.113.99');
        self::assertTrue($this->spam->checkRateLimit());
    }
}
