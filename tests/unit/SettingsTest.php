<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\stars\models\Settings;

/**
 * Covers Settings validation rules.
 */
class SettingsTest extends Unit
{
    protected \UnitTester $tester;

    public function testDefaultsAreValid(): void
    {
        $settings = new Settings();
        self::assertTrue($settings->validate(), print_r($settings->getErrors(), true));
    }

    public function testDefaultStatusMustBePendingOrApproved(): void
    {
        $settings = new Settings();
        $settings->defaultStatus = 'rejected';
        self::assertFalse($settings->validate(['defaultStatus']));

        $settings->defaultStatus = 'approved';
        self::assertTrue($settings->validate(['defaultStatus']));
    }

    public function testMaxRatingBounds(): void
    {
        $settings = new Settings();

        $settings->maxRating = 0;
        self::assertFalse($settings->validate(['maxRating']));

        $settings->maxRating = 11;
        self::assertFalse($settings->validate(['maxRating']));

        $settings->maxRating = 5;
        self::assertTrue($settings->validate(['maxRating']));
    }

    public function testRateLimitAndSubmissionTimeCannotBeNegative(): void
    {
        $settings = new Settings();

        $settings->rateLimitMinutes = -1;
        self::assertFalse($settings->validate(['rateLimitMinutes']));

        $settings->minSubmissionTime = -1;
        self::assertFalse($settings->validate(['minSubmissionTime']));

        $settings->rateLimitMinutes = 0;
        $settings->minSubmissionTime = 0;
        self::assertTrue($settings->validate(['rateLimitMinutes', 'minSubmissionTime']));
    }

    public function testPrivacyFlagsAreBooleans(): void
    {
        $settings = new Settings();
        self::assertTrue($settings->validate(['captureIpAddress', 'captureUserAgent', 'captureReferrer']));
    }
}
