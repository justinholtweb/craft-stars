<?php
/**
 * What an anonymous review or comment can do — checked over HTTP in the plugin-testing harness.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-stars/tests/harness/security.php
 *
 * Until 5.1.2:
 *
 * - a review or comment could be posted against any element id — an unpublished entry, a user;
 * - a reply could hang off a comment on another entry, or one still in moderation;
 * - a review with no rating was saved as the top mark;
 * - the Referer header was stored as given and linked in the CP, `javascript:` and all;
 * - the only rate limit was per entry, and read the stored IP — none at all with IP capture off.
 *
 * Each refusal is paired with what should still work. Runs against the harness's default Stars
 * settings. Self-cleaning.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\db\Query;
use craft\elements\Entry;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\Plugin;

$passed = 0;
$failed = 0;

function check(string $label, callable $test): void
{
    global $passed, $failed;

    try {
        $result = $test();
    } catch (Throwable $e) {
        $result = get_class($e) . ': ' . $e->getMessage();
    }

    if ($result === true) {
        $passed++;
        echo "  ✓ $label\n";
    } else {
        $failed++;
        echo "  ✗ $label\n    " . (is_string($result) ? $result : var_export($result, true)) . "\n";
    }
}

Craft::$app->getPlugins()->loadPlugins();

if (Plugin::getInstance() === null) {
    echo "Stars isn't installed in the harness.\n";
    exit(1);
}

$run = substr(bin2hex(random_bytes(3)), 0, 6);
$cleanup = [];

register_shutdown_function(function() use (&$cleanup, $run) {
    foreach ([Review::find()->reviewerName("*$run*"), Comment::find()->authorName("*$run*")] as $query) {
        foreach ($query->status(null)->all() as $element) {
            Craft::$app->getElements()->deleteElement($element, true);
        }
    }
    foreach ($cleanup as $element) {
        Craft::$app->getElements()->deleteElement($element, true);
    }
});

// A live entry, a second live one, and a disabled one, in any section that has an entry type.
$sectionId = (int)(new Query())->select('sectionId')->from('{{%entries}}')->where(['not', ['sectionId' => null]])->scalar();
$section = Craft::$app->getEntries()->getSectionById($sectionId);
$entry = static function(string $title, bool $enabled) use ($section, &$cleanup): Entry {
    $entry = new Entry(['sectionId' => $section->id, 'typeId' => $section->getEntryTypes()[0]->id, 'title' => $title, 'enabled' => $enabled]);
    Craft::$app->getElements()->saveElement($entry) or throw new RuntimeException(json_encode($entry->getErrors()));

    return $cleanup[] = $entry;
};

$live = $entry("Stars security $run", true);
$other = $entry("Stars security other $run", true);
$disabled = $entry("Stars security draft $run", false);

$http = new Client(['base_uri' => 'http://localhost/', 'cookies' => new CookieJar(), 'http_errors' => false, 'allow_redirects' => false]);
$json = ['Accept' => 'application/json'];

/** Post as an anonymous visitor. Clears the hourly address budget first unless told not to. */
$post = static function(string $action, array $params, array $headers = [], bool $fresh = true) use ($http, $json): array {
    if ($fresh) {
        clearBudget();
        // And the per-entry limit, which reads the IP stored on this run's rows.
        global $run;
        Craft::$app->getDb()->createCommand()->update('{{%stars_reviews}}', ['ipAddress' => null], ['like', 'reviewerName', $run])->execute();
        Craft::$app->getDb()->createCommand()->update('{{%stars_comments}}', ['ipAddress' => null], ['like', 'authorName', $run])->execute();
    }
    $csrf = (string)(json_decode((string)$http->get('index.php?p=actions/users/session-info', ['headers' => $json])->getBody(), true)['csrfTokenValue'] ?? '');

    return json_decode((string)$http->post("index.php?p=actions/stars/$action", [
        'headers' => $json + $headers,
        'form_params' => $params + ['CRAFT_CSRF_TOKEN' => $csrf, 'starsHoneypot' => '', '__stars_ts' => time() - 30],
    ])->getBody(), true) ?? [];
};

function clearBudget(): void
{
    // Requests here come from inside the container; clear every address it might be charged to.
    foreach (['127.0.0.1', '::1', '::1/64', '0:0:0:0::/64', '::/64', gethostbyname(gethostname())] as $ip) {
        Craft::$app->getCache()->delete(sprintf('stars:budget:%s:%d', sha1($ip), intdiv(time(), 3600)));
    }
}

$review = static fn(int $entryId, string $who, array $extra = []) => $extra + [
    'entryId' => $entryId, 'rating' => 4, 'reviewText' => 'Fine.', 'reviewerName' => "$who $run", 'reviewerEmail' => "stars-$run@example.com",
];
$comment = static fn(int $entryId, string $who, array $extra = []) => $extra + [
    'entryId' => $entryId, 'body' => 'Hello.', 'authorName' => "$who $run", 'authorEmail' => "stars-$run@example.com",
];
$saved = static fn(array $response) => ($response['success'] ?? null) === true;
// A refusal for the reason under test — not the rate limit or the spam checks.
$refused = static fn(array $response, string $why) => ($response['success'] ?? null) === false && str_contains((string)($response['error'] ?? ''), $why);

echo "\nWhat a review can be posted against\n";

check('a live entry', fn() => $saved($r = $post('reviews/save', $review($live->id, 'Live'))) ?: json_encode($r));
check('…not a disabled one', fn() => $refused($r = $post('reviews/save', $review($disabled->id, 'Disabled')), 'can’t be reviewed') ?: json_encode($r));
check('…not an element that isn’t an entry', fn() => $refused($r = $post('reviews/save', $review(1, 'User')), 'can’t be reviewed') ?: json_encode($r));
check('…not nothing', fn() => $refused($r = $post('reviews/save', $review(0, 'Nothing')), 'can’t be reviewed') ?: json_encode($r));
check('…and the comment form agrees', fn() => $refused($r = $post('comments/save', $comment($disabled->id, 'Disabled')), 'can’t be commented on') ?: json_encode($r));

echo "\nRatings\n";

check('a review with no rating is refused, not given full marks', function() use ($post, $review, $other, $run) {
    $params = $review($other->id, 'Unrated');
    unset($params['rating']);
    $response = $post('reviews/save', $params);

    return ($response['error'] ?? null) === 'Please choose a rating.'
        && !Review::find()->reviewerName("Unrated $run")->status(null)->exists() ?: json_encode($response);
});

echo "\nReplies\n";

$approvedParent = new Comment(['entryId' => $live->id, 'body' => 'Parent', 'authorName' => "Parent $run", 'commentStatus' => 'approved']);
$pendingParent = new Comment(['entryId' => $live->id, 'body' => 'Pending', 'authorName' => "Pending $run", 'commentStatus' => 'pending']);
Craft::$app->getElements()->saveElement($approvedParent) or throw new RuntimeException(json_encode($approvedParent->getErrors()));
Craft::$app->getElements()->saveElement($pendingParent) or throw new RuntimeException(json_encode($pendingParent->getErrors()));

check('a reply to an approved comment on the same entry', fn() => $saved($r = $post('comments/save', $comment($live->id, 'Reply', ['parentId' => $approvedParent->id]))) ?: json_encode($r));
check('…not to a comment on another entry', fn() => $refused($r = $post('comments/save', $comment($other->id, 'Cross', ['parentId' => $approvedParent->id])), 'can’t be replied to') ?: json_encode($r));
check('…nor one still in moderation', fn() => $refused($r = $post('comments/save', $comment($live->id, 'Early', ['parentId' => $pendingParent->id])), 'can’t be replied to') ?: json_encode($r));

echo "\nThe Referer\n";

check('a `javascript:` referrer isn’t stored', function() use ($post, $comment, $other, $run) {
    $response = $post('comments/save', $comment($other->id, 'Referrer'), ['Referer' => 'javascript:alert(document.cookie)']);
    $stored = Comment::find()->authorName("Referrer $run")->status(null)->one();

    return ($response['success'] ?? null) === true && $stored && $stored->submissionUrl === null ?: json_encode([$response, $stored?->submissionUrl]);
});

check('…a web address is', function() use ($post, $review, $disabled, $live, $run) {
    // A second review of $live from this address would be stopped by the per-entry limit; use a fresh entry.
    $fresh = new Entry(['sectionId' => $live->sectionId, 'typeId' => $live->typeId, 'title' => "Stars security ref $run"]);
    Craft::$app->getElements()->saveElement($fresh);
    $GLOBALS['cleanup'][] = $fresh;
    $response = $post('reviews/save', $review($fresh->id, 'Referred'), ['Referer' => 'https://example.com/widget']);
    $stored = Review::find()->reviewerName("Referred $run")->status(null)->one();

    return $stored?->submissionUrl === 'https://example.com/widget' ?: json_encode([$response, $stored?->submissionUrl]);
});

echo "\nThe hourly budget\n";

check('one address runs out across entries, however many there are', function() use ($post, $review, $live, $run) {
    clearBudget();
    $limit = Plugin::getInstance()->getSettings()->maxSubmissionsPerHour;

    // Each attempt on a different (non-existent) entry spends from the budget even though it's refused.
    for ($i = 0; $i < $limit; $i++) {
        $post('reviews/save', $review(999999000 + $i, 'Spray'), [], false);
    }

    $last = $post('reviews/save', $review($live->id, 'Spray last', ['rating' => 3]), [], false);

    return ($last['success'] ?? null) === false && str_contains((string)($last['error'] ?? ''), 'recently') ?: json_encode($last);
});

check('…and a forged X-Forwarded-For doesn’t buy another', function() use ($post, $review, $live) {
    $response = $post('reviews/save', $review($live->id, 'Forged', ['rating' => 3]), ['X-Forwarded-For' => '203.0.113.' . random_int(1, 254)], false);

    return str_contains((string)($response['error'] ?? ''), 'recently') ?: json_encode($response);
});

clearBudget();

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
