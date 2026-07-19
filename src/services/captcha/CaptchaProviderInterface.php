<?php

namespace justinholtweb\stars\services\captcha;

interface CaptchaProviderInterface
{
    /**
     * Verify a captcha token server-side. Returns true if the token is valid.
     */
    public function verify(string $token, ?string $remoteIp = null): bool;

    /**
     * The POST body field the frontend widget submits the token in.
     */
    public function tokenField(): string;

    /**
     * The stable settings handle for this provider (e.g. 'hcaptcha').
     */
    public static function handle(): string;

    /**
     * Human-readable name for the settings dropdown.
     */
    public static function displayName(): string;
}
