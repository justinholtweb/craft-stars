<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\BlockService;

/**
 * Covers the blocklist service: matching, normalization, and idempotent writes.
 */
class BlockServiceTest extends Unit
{
    protected \UnitTester $tester;

    private BlockService $block;

    protected function _before(): void
    {
        $this->block = Plugin::getInstance()->block;
    }

    public function testUnknownSubmitterIsNotBlocked(): void
    {
        self::assertFalse($this->block->isBlocked('nobody@example.com', '1.2.3.4', 42));
    }

    public function testNoIdentifiersIsNotBlocked(): void
    {
        $this->block->block(BlockService::TYPE_EMAIL, 'someone@example.com');
        self::assertFalse($this->block->isBlocked(null, null, null));
    }

    public function testBlockByEmailIsCaseInsensitive(): void
    {
        $this->block->block(BlockService::TYPE_EMAIL, 'Spammer@Example.com');

        self::assertTrue($this->block->isBlocked('spammer@example.com'));
        self::assertTrue($this->block->isBlocked('SPAMMER@EXAMPLE.COM'));
        self::assertFalse($this->block->isBlocked('someone-else@example.com'));
    }

    public function testBlockByIp(): void
    {
        $this->block->block(BlockService::TYPE_IP, '203.0.113.7');

        self::assertTrue($this->block->isBlocked(null, '203.0.113.7'));
        self::assertFalse($this->block->isBlocked(null, '203.0.113.8'));
    }

    public function testBlockByUserId(): void
    {
        $this->block->block(BlockService::TYPE_USER, '99');

        self::assertTrue($this->block->isBlocked(null, null, 99));
        self::assertFalse($this->block->isBlocked(null, null, 100));
    }

    public function testAnySingleMatchBlocks(): void
    {
        $this->block->block(BlockService::TYPE_IP, '198.51.100.5');

        // Email + user are clean, but the IP matches → blocked.
        self::assertTrue($this->block->isBlocked('clean@example.com', '198.51.100.5', 1));
    }

    public function testBlockIsIdempotentAndUnblockWorks(): void
    {
        $this->block->block(BlockService::TYPE_EMAIL, 'dupe@example.com');
        $this->block->block(BlockService::TYPE_EMAIL, 'dupe@example.com');

        self::assertTrue($this->block->isBlocked('dupe@example.com'));

        $this->block->unblock(BlockService::TYPE_EMAIL, 'DUPE@example.com');
        self::assertFalse($this->block->isBlocked('dupe@example.com'));
    }

    public function testGetAllReturnsEntries(): void
    {
        $this->block->block(BlockService::TYPE_EMAIL, 'a@example.com');
        $this->block->block(BlockService::TYPE_IP, '10.0.0.1');

        self::assertGreaterThanOrEqual(2, count($this->block->getAll()));
    }
}
