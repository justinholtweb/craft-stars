<?php

namespace justinholtweb\stars\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\stars\elements\base\ModeratedElement;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\elements\Review;

/**
 * The submission URL is the browser's Referer header — whatever a script sends. Until 5.1.2 it was
 * stored as given and linked in the review's sidebar, so a `javascript:` value ran in the
 * moderator's session when they clicked it.
 */
class SubmissionUrlTest extends Unit
{
    protected \UnitTester $tester;

    public function testOnlyWebAddressesAreKept(): void
    {
        self::assertSame('https://example.com/widget?a=1', ModeratedElement::safeSubmissionUrl('https://example.com/widget?a=1'));
        self::assertSame('http://example.com/', ModeratedElement::safeSubmissionUrl(' http://example.com/ '));

        foreach (['javascript://%0aalert(1)', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,<script>1</script>', '//evil.example/', 'vbscript:x', '', null] as $bad) {
            self::assertNull(ModeratedElement::safeSubmissionUrl($bad), var_export($bad, true));
        }

        self::assertNull(ModeratedElement::safeSubmissionUrl('https://example.com/' . str_repeat('a', 2100)));
    }

    public function testAStoredScriptUrlIsShownAsTextNotALink(): void
    {
        foreach ([new Review(), new Comment()] as $element) {
            $element->submissionUrl = 'javascript:alert(document.cookie)';
            $html = $this->metadataHtml($element);

            self::assertStringNotContainsString('href="javascript', $html, $element::class);
            self::assertStringContainsString('javascript:alert(document.cookie)', $html, $element::class);
        }
    }

    public function testAWebAddressIsStillALink(): void
    {
        foreach ([new Review(), new Comment()] as $element) {
            $element->submissionUrl = 'https://example.com/widget';

            self::assertStringContainsString('href="https://example.com/widget"', $this->metadataHtml($element), $element::class);
        }
    }

    private function metadataHtml(ModeratedElement $element): string
    {
        // What the element editor's sidebar prints, so a revert in either element is caught.
        $method = new \ReflectionMethod($element, 'metadata');

        return implode("\n", array_map('strval', $method->invoke($element)));
    }
}
