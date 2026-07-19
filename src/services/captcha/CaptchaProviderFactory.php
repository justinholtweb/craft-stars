<?php

namespace justinholtweb\stars\services\captcha;

use GuzzleHttp\Client;
use justinholtweb\stars\models\Settings;

class CaptchaProviderFactory
{
    public const PROVIDERS = [
        RecaptchaV3Provider::class,
        RecaptchaV2Provider::class,
        HcaptchaProvider::class,
        TurnstileProvider::class,
    ];

    /**
     * Build the configured captcha provider, or null if none is active.
     *
     * Honors the legacy `enableRecaptcha` + `recaptcha*` settings by mapping
     * them onto reCAPTCHA v3.
     */
    public static function fromSettings(Settings $settings, ?Client $client = null): ?CaptchaProviderInterface
    {
        $provider = $settings->captchaProvider;
        $secret = $settings->captchaSecretKey;

        // Backwards compatibility: legacy reCAPTCHA toggle.
        if (($provider === 'none' || $provider === '') && $settings->enableRecaptcha) {
            $provider = RecaptchaV3Provider::handle();
        }

        if ($provider === 'none' || $provider === '') {
            return null;
        }

        // Prefer the new key, fall back to the legacy reCAPTCHA secret.
        $secret = $secret ?: $settings->recaptchaSecretKey;

        return match ($provider) {
            RecaptchaV3Provider::handle() => new RecaptchaV3Provider($secret, (float)$settings->recaptchaThreshold, $client),
            RecaptchaV2Provider::handle() => new RecaptchaV2Provider($secret, $client),
            HcaptchaProvider::handle() => new HcaptchaProvider($secret, $client),
            TurnstileProvider::handle() => new TurnstileProvider($secret, $client),
            default => null,
        };
    }

    /**
     * The effective provider handle + public site key for the frontend widget,
     * or null when no captcha is active.
     *
     * @return array{provider: string, siteKey: string}|null
     */
    public static function frontendConfig(Settings $settings): ?array
    {
        $provider = $settings->captchaProvider;
        $siteKey = $settings->captchaSiteKey;

        if (($provider === 'none' || $provider === '') && $settings->enableRecaptcha) {
            $provider = RecaptchaV3Provider::handle();
            $siteKey = $siteKey ?: $settings->recaptchaSiteKey;
        }

        if ($provider === 'none' || $provider === '') {
            return null;
        }

        return [
            'provider' => $provider,
            'siteKey' => $siteKey ?: $settings->recaptchaSiteKey,
        ];
    }
}
