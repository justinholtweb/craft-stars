<?php

namespace justinholtweb\stars\services\captcha;

class TurnstileProvider extends BaseCaptchaProvider
{
    protected function endpoint(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    public function tokenField(): string
    {
        return 'cf-turnstile-response';
    }

    public static function handle(): string
    {
        return 'turnstile';
    }

    public static function displayName(): string
    {
        return 'Cloudflare Turnstile';
    }
}
