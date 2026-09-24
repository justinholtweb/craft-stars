<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Stub;
use Codeception\Test\Unit;
use Craft;
use craft\helpers\Db;
use craft\web\Request;
use DateTime;
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
    use CreatesComments;

    protected \UnitTester $tester;

    private SpamService $spam;

    private string $originalTimeZone;

    protected function _before(): void
    {
        $this->spam = Plugin::getInstance()->spam;
        $this->originalTimeZone = Craft::$app->getTimeZone();
    }

    protected function _after(): void
    {
        Craft::$app->setTimeZone($this->originalTimeZone);
    }

    /**
     * Backdate a submission. Craft stores dateCreated in UTC, which is what the
     * rate limiter has to compare against.
     */
    private function backdate(string $table, int $id, string $modifier): void
    {
        Craft::$app->getDb()->createCommand()
            ->update($table, ['dateCreated' => Db::prepareDateForDb(new DateTime($modifier))], ['id' => $id])
            ->execute();
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

    public function testRateLimitContextSelectsTheRightTable(): void
    {
        Plugin::getInstance()->getSettings()->rateLimitMinutes = 1440;

        // A recent comment (but no review) from this IP.
        $this->createComment(['ipAddress' => '203.0.113.55']);
        $this->fakeRequest([], '203.0.113.55');

        // Blocked in the comments context, allowed in the reviews context.
        self::assertFalse($this->spam->checkRateLimit('comments'));
        self::assertTrue($this->spam->checkRateLimit('reviews'));
    }

    public function testRateLimitWindowIsMeasuredInUtcWestOfUtc(): void
    {
        // A site behind UTC used to stretch the window by the offset: a review
        // two hours old still counted against a one-hour limit.
        Craft::$app->setTimeZone('America/New_York');
        Plugin::getInstance()->getSettings()->rateLimitMinutes = 60;
        $review = $this->createReview(['ipAddress' => '203.0.113.21']);
        $this->backdate('{{%stars_reviews}}', $review->id, '-2 hours');

        $this->fakeRequest([], '203.0.113.21');
        self::assertTrue($this->spam->checkRateLimit());
    }

    public function testRateLimitWindowIsMeasuredInUtcEastOfUtc(): void
    {
        // A site ahead of UTC used to put the cutoff in the future, so nothing
        // was ever rate-limited.
        Craft::$app->setTimeZone('Asia/Tokyo');
        Plugin::getInstance()->getSettings()->rateLimitMinutes = 60;
        $this->createReview(['ipAddress' => '203.0.113.22']);

        $this->fakeRequest([], '203.0.113.22');
        self::assertFalse($this->spam->checkRateLimit());
    }

    public function testCheckReportsRateLimitSeparatelyFromSpam(): void
    {
        Plugin::getInstance()->getSettings()->rateLimitMinutes = 60;
        Plugin::getInstance()->getSettings()->minSubmissionTime = 0;
        $this->createComment(['ipAddress' => '203.0.113.23']);

        $this->fakeRequest(['starsHoneypot' => ''], '203.0.113.23');
        self::assertSame(SpamService::FAILURE_RATE_LIMIT, $this->spam->check('comments'));
        self::assertTrue($this->spam->isSpam('comments'));
    }

    public function testCheckReportsHoneypot(): void
    {
        $this->fakeRequest(['starsHoneypot' => 'i-am-a-bot'], '203.0.113.24');
        self::assertSame(SpamService::FAILURE_HONEYPOT, $this->spam->check());
    }

    public function testCheckPassesACleanSubmission(): void
    {
        Plugin::getInstance()->getSettings()->rateLimitMinutes = 60;
        Plugin::getInstance()->getSettings()->minSubmissionTime = 3;

        $this->fakeRequest(['starsHoneypot' => '', '__stars_ts' => time() - 10], '198.51.100.77');
        self::assertNull($this->spam->check());
        self::assertFalse($this->spam->isSpam());
    }
}
