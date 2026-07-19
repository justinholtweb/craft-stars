<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\NotificationService;
use ReflectionMethod;

/**
 * Covers the log email-masking helper, which keeps full addresses out of logs.
 */
class NotificationServiceTest extends Unit
{
    protected \UnitTester $tester;

    private function mask(string $email): string
    {
        $method = new ReflectionMethod(NotificationService::class, '_maskEmail');
        $method->setAccessible(true);

        return $method->invoke(Plugin::getInstance()->notifications, $email);
    }

    public function testMasksTypicalAddress(): void
    {
        self::assertSame('a***@example.com', $this->mask('alice@example.com'));
    }

    public function testMasksSingleCharLocalPart(): void
    {
        self::assertSame('x***@y.com', $this->mask('x@y.com'));
    }

    public function testMalformedAddressesAreFullyMasked(): void
    {
        self::assertSame('***', $this->mask('noatsign'));
        self::assertSame('***', $this->mask('@example.com'));
    }
}
