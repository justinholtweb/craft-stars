<?php

namespace justinholtweb\stars\services\captcha;

class RecaptchaV2Provider extends BaseCaptchaProvider
{
    protected function endpoint(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }

    public static function handle(): string
    {
        return 'recaptcha_v2';
    }

    public static function displayName(): string
    {
        return 'reCAPTCHA v2';
    }
}
