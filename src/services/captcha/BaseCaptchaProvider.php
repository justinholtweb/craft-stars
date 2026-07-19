<?php

namespace justinholtweb\stars\services\captcha;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;

/**
 * Shared server-side verification for token-based captcha providers. Each
 * provider POSTs {secret, response, remoteip} to a siteverify endpoint and
 * checks the JSON `success` flag (plus any provider-specific extra check).
 */
abstract class BaseCaptchaProvider implements CaptchaProviderInterface
{
    public function __construct(
        protected string $secretKey,
        protected ?Client $client = null,
    ) {
    }

    /**
     * The provider's siteverify endpoint.
     */
    abstract protected function endpoint(): string;

    /**
     * Provider-specific extra validation beyond `success` (e.g. v3 score).
     */
    protected function passes(array $result): bool
    {
        return true;
    }

    public function tokenField(): string
    {
        return 'g-recaptcha-response';
    }

    public function verify(string $token, ?string $remoteIp = null): bool
    {
        if (trim($token) === '') {
            return false;
        }

        try {
            $client = $this->client ?? Craft::createGuzzleClient();
            $response = $client->post($this->endpoint(), [
                'form_params' => [
                    'secret' => App::parseEnv($this->secretKey),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ],
            ]);

            $result = json_decode((string)$response->getBody(), true) ?: [];

            return !empty($result['success']) && $this->passes($result);
        } catch (\Throwable $e) {
            Craft::error('Captcha verification error: ' . $e->getMessage(), 'stars');
            return false;
        }
    }
}
