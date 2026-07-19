<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Stub;
use Codeception\Test\Unit;
use craft\elements\Entry;
use craft\helpers\Json;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\SchemaService;

/**
 * Covers JSON-LD generation: shape of the AggregateRating + Review nodes and
 * the empty-state short-circuit.
 */
class SchemaServiceTest extends Unit
{
    use CreatesReviews;

    protected \UnitTester $tester;

    private SchemaService $schema;

    protected function _before(): void
    {
        $this->schema = Plugin::getInstance()->schema;
    }

    private function makeEntry(int $id): Entry
    {
        return Stub::make(Entry::class, [
            'id' => $id,
            'title' => 'Widget',
            'getUrl' => fn() => 'https://example.com/widget',
        ]);
    }

    public function testReturnsEmptyStringWhenNoReviews(): void
    {
        $host = $this->createReview(['reviewStatus' => 'pending']);
        $entry = $this->makeEntry($host->id);

        self::assertSame('', $this->schema->generateJsonLd($entry));
    }

    public function testGeneratesAggregateRatingAndReviews(): void
    {
        $host = $this->createReview(['reviewStatus' => 'pending']);
        $entryId = $host->id;

        $this->createReview(['entryId' => $entryId, 'rating' => 5, 'reviewText' => 'Great']);
        $this->createReview(['entryId' => $entryId, 'rating' => 4]);
        $this->createReview(['entryId' => $entryId, 'rating' => 3]);

        $output = $this->schema->generateJsonLd($this->makeEntry($entryId));

        self::assertStringContainsString('<script type="application/ld+json">', $output);

        $json = trim(str_replace(
            ['<script type="application/ld+json">', '</script>'],
            '',
            $output
        ));
        $data = Json::decode($json);

        self::assertSame('https://schema.org', $data['@context']);
        self::assertSame('Product', $data['@type']);
        self::assertSame('Widget', $data['name']);

        // AggregateRating
        self::assertSame('AggregateRating', $data['aggregateRating']['@type']);
        self::assertSame(3, $data['aggregateRating']['reviewCount']);
        self::assertEquals(4.0, $data['aggregateRating']['ratingValue']);
        self::assertSame(5, $data['aggregateRating']['bestRating']);
        self::assertSame(1, $data['aggregateRating']['worstRating']);

        // Individual reviews
        self::assertCount(3, $data['review']);
        self::assertSame('Review', $data['review'][0]['@type']);
        self::assertSame('Person', $data['review'][0]['author']['@type']);
    }
}
