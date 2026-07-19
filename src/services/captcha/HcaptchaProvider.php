<?php

namespace justinholtweb\stars\services\captcha;

class HcaptchaProvider extends BaseCaptchaProvider
{
    protected function endpoint(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }

    public function tokenField(): string
    {
        return 'h-captcha-response';
    }

    public static function handle(): string
    {
        return 'hcaptcha';
    }

    public static function displayName(): string
    {
        return 'hCaptcha';
    }
}
