<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use justinholtweb\stars\models\Settings;
use justinholtweb\stars\services\captcha\CaptchaProviderFactory;
use justinholtweb\stars\services\captcha\HcaptchaProvider;
use justinholtweb\stars\services\captcha\RecaptchaV2Provider;
use justinholtweb\stars\services\captcha\RecaptchaV3Provider;
use justinholtweb\stars\services\captcha\TurnstileProvider;

/**
 * Covers the captcha provider abstraction: server-side verification for each
 * provider (with mocked HTTP) and the factory's provider resolution.
 */
class CaptchaProviderTest extends Unit
{
    protected \UnitTester $tester;

    private function client(array $queue): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($queue))]);
    }

    private function ok(array $body): Response
    {
        return new Response(200, [], json_encode($body));
    }

    public function testRecaptchaV3PassesAboveThreshold(): void
    {
        $provider = new RecaptchaV3Provider('secret', 0.5, $this->client([$this->ok(['success' => true, 'score' => 0.9])]));
        self::assertTrue($provider->verify('token', '1.2.3.4'));
    }

    public function testRecaptchaV3FailsBelowThreshold(): void
    {
        $provider = new RecaptchaV3Provider('secret', 0.5, $this->client([$this->ok(['success' => true, 'score' => 0.3])]));
        self::assertFalse($provider->verify('token', '1.2.3.4'));
    }

    public function testRecaptchaV3FailsWhenUnsuccessful(): void
    {
        $provider = new RecaptchaV3Provider('secret', 0.5, $this->client([$this->ok(['success' => false])]));
        self::assertFalse($provider->verify('token'));
    }

    public function testEmptyTokenFailsWithoutHttpCall(): void
    {
        // Empty queue: if an HTTP call were made, MockHandler would throw.
        $provider = new RecaptchaV3Provider('secret', 0.5, $this->client([]));
        self::assertFalse($provider->verify('   '));
    }

    public function testTransportErrorFails(): void
    {
        $provider = new HcaptchaProvider('secret', $this->client([
            new ConnectException('boom', new Request('POST', 'siteverify')),
        ]));
        self::assertFalse($provider->verify('token'));
    }

    public function testHcaptchaAndTurnstilePassOnSuccess(): void
    {
        $hcaptcha = new HcaptchaProvider('secret', $this->client([$this->ok(['success' => true])]));
        self::assertTrue($hcaptcha->verify('token'));
        self::assertSame('h-captcha-response', $hcaptcha->tokenField());

        $turnstile = new TurnstileProvider('secret', $this->client([$this->ok(['success' => true])]));
        self::assertTrue($turnstile->verify('token'));
        self::assertSame('cf-turnstile-response', $turnstile->tokenField());
    }

    public function testRecaptchaV2IgnoresScore(): void
    {
        $provider = new RecaptchaV2Provider('secret', $this->client([$this->ok(['success' => true])]));
        self::assertTrue($provider->verify('token'));
    }

    public function testFactoryReturnsNullWhenDisabled(): void
    {
        $settings = new Settings();
        $settings->captchaProvider = 'none';
        self::assertNull(CaptchaProviderFactory::fromSettings($settings));
    }

    public function testFactorySelectsProviderByHandle(): void
    {
        $settings = new Settings();
        $settings->captchaProvider = 'hcaptcha';
        self::assertInstanceOf(HcaptchaProvider::class, CaptchaProviderFactory::fromSettings($settings));
    }

    public function testFactoryMapsLegacyRecaptchaToggle(): void
    {
        $settings = new Settings();
        $settings->captchaProvider = 'none';
        $settings->enableRecaptcha = true;
        $settings->recaptchaSecretKey = 'legacy-secret';

        self::assertInstanceOf(RecaptchaV3Provider::class, CaptchaProviderFactory::fromSettings($settings));
    }

    public function testFrontendConfig(): void
    {
        $settings = new Settings();
        self::assertNull(CaptchaProviderFactory::frontendConfig($settings));

        $settings->captchaProvider = 'turnstile';
        $settings->captchaSiteKey = 'site-key';
        $config = CaptchaProviderFactory::frontendConfig($settings);
        self::assertSame('turnstile', $config['provider']);
        self::assertSame('site-key', $config['siteKey']);
    }
}
