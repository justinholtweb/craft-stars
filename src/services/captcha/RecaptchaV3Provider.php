<?php

namespace justinholtweb\stars\services\captcha;

use GuzzleHttp\Client;

class RecaptchaV3Provider extends BaseCaptchaProvider
{
    public function __construct(string $secretKey, private float $threshold = 0.5, ?Client $client = null)
    {
        parent::__construct($secretKey, $client);
    }

    protected function endpoint(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }

    protected function passes(array $result): bool
    {
        return ($result['score'] ?? 0) >= $this->threshold;
    }

    public static function handle(): string
    {
        return 'recaptcha_v3';
    }

    public static function displayName(): string
    {
        return 'reCAPTCHA v3';
    }
}
