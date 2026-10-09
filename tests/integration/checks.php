<?php
/**
 * Stars integration checks — stored rating summaries, the Stars Rating field, its condition rule,
 * GraphQL and the JSON-LD.
 *
 * Run inside the plugin-testing container, from the site root:
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-stars/tests/integration/checks.php
 *
 * Covers what the Codeception unit suite cannot: real element saves through Craft (so the summary
 * table follows moderation), element queries ordered and filtered by a real field in a real layout,
 * GraphQL executed against schemas with and without the Stars components, the console command, and
 * the injected JSON-LD on a rendered entry page.
 *
 * Self-cleaning: the field, entry type, section, template, entries, reviews and comments it creates
 * are deleted at the end. Settings are changed in memory only, except the HTTP injection check,
 * which writes a temporary `config/stars.php` (skipped if the harness already has one).
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\db\Query;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use GuzzleHttp\Client;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\events\DefineSchemaEvent;
use justinholtweb\stars\fields\conditions\StarsRatingConditionRule;
use justinholtweb\stars\fields\StarsRating;
use justinholtweb\stars\models\RatingSummary;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\RatingService;
use justinholtweb\stars\services\SchemaService;
use yii\base\Event;

$passed = 0;
$failed = 0;

function check(string $label, callable $test): void
{
    global $passed, $failed;

    try {
        $result = $test();

        if ($result === true) {
            $passed++;
            echo "  ✓ $label\n";
            return;
        }

        $failed++;
        echo "  ✗ $label\n    " . (is_string($result) ? $result : 'returned ' . var_export($result, true)) . "\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✗ $label\n    " . get_class($e) . ': ' . $e->getMessage() . "\n    " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}

function section(string $title): void
{
    echo "\n$title\n";
}

Craft::$app->getPlugins()->loadPlugins();
$plugin = Plugin::getInstance();

if ($plugin === null) {
    echo "Stars isn't installed in the harness.\n";
    exit(1);
}

if (!Craft::$app->getDb()->tableExists(RatingService::TABLE)) {
    echo "The stars_ratings table is missing — run `php craft migrate/up --plugin=stars`.\n";
    exit(1);
}

$settings = $plugin->getSettings();
$originalSettings = $settings->toArray();
$suffix = substr(md5((string)microtime(true)), 0, 6);
$elements = Craft::$app->getElements();
$ratings = $plugin->ratings;
$fixtures = ['section' => null, 'entryType' => null, 'field' => null, 'template' => null, 'config' => null];

/** Fresh summary straight from the table, bypassing the per-request memo. */
function stored(int $entryId): array|false
{
    $row = (new Query())->select(['average', 'count'])->from(RatingService::TABLE)->where(['elementId' => $entryId])->one();

    return $row ? ['average' => (float)$row['average'], 'count' => (int)$row['count']] : false;
}

function review(int $entryId, int $rating, string $status, string $name, array $extra = []): Review
{
    global $suffix;
    $review = new Review(array_merge([
        'entryId' => $entryId,
        'rating' => $rating,
        'reviewStatus' => $status,
        'reviewerName' => $name === '' ? '' : "$name $suffix",
        'reviewerEmail' => "stars-$suffix@example.com",
        'reviewText' => "Review $suffix",
    ], $extra));

    // A nameless review can't pass validation, but older rows (and imports) hold them.
    Craft::$app->getElements()->saveElement($review, $name !== '') or throw new RuntimeException(json_encode($review->getErrors()));

    return $review;
}

try {
    // -------------------------------------------------------------------------------- fixtures

    section('Fixtures');

    $field = new StarsRating();
    $field->name = "Stars Rating $suffix";
    $field->handle = "starsRating$suffix";
    Craft::$app->getFields()->saveField($field) or throw new RuntimeException(json_encode($field->getErrors()));
    $fixtures['field'] = $field;
    $handle = $field->handle;

    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => 'Content']);
    $tab->setLayout($layout);
    $tab->setElements([new EntryTitleField(), new CustomField($field)]);
    $layout->setTabs([$tab]);

    $entryType = new EntryType(['name' => "Stars Type $suffix", 'handle' => "starsType$suffix"]);
    $entryType->setFieldLayout($layout);
    Craft::$app->getEntries()->saveEntryType($entryType) or throw new RuntimeException(json_encode($entryType->getErrors()));
    $fixtures['entryType'] = $entryType;

    $templateName = "stars-check-$suffix";
    $templatePath = Craft::getAlias('@templates') . "/$templateName.twig";
    file_put_contents($templatePath, <<<'TWIG'
<!doctype html>
<html><head><title>{{ entry.title }}</title>{% if 'Own' in entry.title %}{{ craft.stars.reviews.schemaOrg(entry)|raw }}{% endif %}</head>
<body><h1>{{ entry.title }}</h1></body></html>
TWIG);
    $fixtures['template'] = $templatePath;

    $section = new Section([
        'name' => "Stars Section $suffix",
        'handle' => "starsSection$suffix",
        'type' => Section::TYPE_CHANNEL,
    ]);
    $section->setSiteSettings([
        new Section_SiteSettings([
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'hasUrls' => true,
            'uriFormat' => "stars-check-$suffix/{slug}",
            'template' => $templateName,
            'enabledByDefault' => true,
        ]),
    ]);
    $section->setEntryTypes([$entryType]);
    Craft::$app->getEntries()->saveSection($section) or throw new RuntimeException(json_encode($section->getErrors()));
    $fixtures['section'] = $section;

    $makeEntry = function(string $title, bool $enabled = true) use ($section, $entryType, $elements): Entry {
        $entry = new Entry(['sectionId' => $section->id, 'typeId' => $entryType->id, 'title' => $title, 'enabled' => $enabled]);
        $elements->saveElement($entry) or throw new RuntimeException(json_encode($entry->getErrors()));

        return $entry;
    };

    $a = $makeEntry('Alpha');
    $b = $makeEntry('Bravo');
    $c = $makeEntry('Charlie');
    $own = $makeEntry('Own Delta');
    $hidden = $makeEntry('Echo', false);

    check('fixtures are in place', fn() => $a->id && $b->id && $c->id && $own->id && $hidden->id && $field->id);

    // ------------------------------------------------------------------------ stored summaries

    section('Stored summaries follow moderation');

    check('the migration backfill matches the live aggregate for every reviewed entry', function() use ($plugin) {
        $entryIds = (new Query())->select(['entryId'])->distinct()->from('{{%stars_reviews}}')
            ->where(['reviewStatus' => 'approved'])->andWhere(['not', ['entryId' => null]])->column();

        foreach ($entryIds as $entryId) {
            $live = ['average' => $plugin->reviews->getAverageRating((int)$entryId), 'count' => $plugin->reviews->getReviewCount((int)$entryId)];
            $row = stored((int)$entryId);

            if ($live['count'] === 0 ? $row !== false : (!$row || round($row['average'], 1) !== $live['average'] || $row['count'] !== $live['count'])) {
                return "entry $entryId: stored " . json_encode($row) . ' live ' . json_encode($live);
            }
        }

        return true;
    });

    $revisionsBefore = (int)(new Query())->from('{{%revisions}}')->where(['canonicalId' => $a->id])->count();
    $dateUpdatedBefore = (new Query())->select('dateUpdated')->from('{{%elements}}')->where(['id' => $a->id])->scalar();

    $r1 = review($a->id, 5, 'approved', 'Ann');
    $r2 = review($a->id, 4, 'approved', 'Bob');
    $r3 = review($a->id, 1, 'pending', 'Cat');

    check('two approved reviews and a pending one: 4.5 from 2', fn() => stored($a->id) === ['average' => 4.5, 'count' => 2] ?: json_encode(stored($a->id)));

    check('moderation does not save the entry (no revision, dateUpdated untouched)', function() use ($a, $revisionsBefore, $dateUpdatedBefore) {
        $revisions = (int)(new Query())->from('{{%revisions}}')->where(['canonicalId' => $a->id])->count();
        $dateUpdated = (new Query())->select('dateUpdated')->from('{{%elements}}')->where(['id' => $a->id])->scalar();

        return $revisions === $revisionsBefore && $dateUpdated === $dateUpdatedBefore ?: "revisions $revisionsBefore → $revisions, dateUpdated $dateUpdatedBefore → $dateUpdated";
    });

    check('approving the pending review brings it in: 3.33 from 3', function() use ($r3, $elements, $a) {
        $r3->reviewStatus = 'approved';
        $elements->saveElement($r3);

        return stored($a->id) === ['average' => 3.33, 'count' => 3] ?: json_encode(stored($a->id));
    });

    check('rejecting it takes it out again', function() use ($r3, $elements, $a) {
        $r3->reviewStatus = 'rejected';
        $elements->saveElement($r3);

        return stored($a->id) === ['average' => 4.5, 'count' => 2] ?: json_encode(stored($a->id));
    });

    check('editing an approved review’s rating moves the average', function() use ($r2, $elements, $a) {
        $r2->rating = 2;
        $elements->saveElement($r2);

        return stored($a->id) === ['average' => 3.5, 'count' => 2] ?: json_encode(stored($a->id));
    });

    check('moving a review to another entry updates both entries', function() use ($r2, $elements, $a, $b) {
        $r2->entryId = $b->id;
        $elements->saveElement($r2);

        return stored($a->id) === ['average' => 5.0, 'count' => 1] && stored($b->id) === ['average' => 2.0, 'count' => 1]
            ?: json_encode([stored($a->id), stored($b->id)]);
    });

    check('trashing the last review removes the row; restoring brings it back', function() use ($r2, $elements, $b) {
        $elements->deleteElement($r2);
        $afterDelete = stored($b->id);
        $elements->restoreElement($r2);
        $afterRestore = stored($b->id);

        return $afterDelete === false && $afterRestore === ['average' => 2.0, 'count' => 1] ?: json_encode([$afterDelete, $afterRestore]);
    });

    review($b->id, 4, 'approved', 'Dan');
    review($c->id, 3, 'approved', 'Eve');
    review($own->id, 5, 'approved', 'Fay');
    review($own->id, 4, 'approved', '');
    review($hidden->id, 5, 'approved', 'Gus');

    check('a pending review arriving does not touch the table', function() use ($c) {
        $before = (new Query())->select('dateUpdated')->from(RatingService::TABLE)->where(['elementId' => $c->id])->scalar();
        sleep(1);
        review($c->id, 1, 'pending', 'Hal');
        $after = (new Query())->select('dateUpdated')->from(RatingService::TABLE)->where(['elementId' => $c->id])->scalar();

        return $before === $after ?: "$before → $after";
    });

    // ------------------------------------------------------------------------ console command

    section('stars/ratings/rebuild');

    check('the command repairs tampered and missing rows', function() use ($a, $c, $hidden) {
        $db = Craft::$app->getDb();
        $db->createCommand()->update(RatingService::TABLE, ['average' => 1, 'count' => 99], ['elementId' => $a->id])->execute();
        $db->createCommand()->delete(RatingService::TABLE, ['elementId' => $c->id])->execute();
        $db->createCommand()->update(RatingService::TABLE, ['average' => 2, 'count' => 7], ['elementId' => $hidden->id])->execute();

        exec('php craft stars/ratings/rebuild 2>&1', $out, $code);

        return $code === 0 && stored($a->id) === ['average' => 5.0, 'count' => 1] && stored($c->id) === ['average' => 3.0, 'count' => 1]
            && stored($hidden->id) === ['average' => 5.0, 'count' => 1]
            ?: "exit $code: " . implode(' ', $out) . ' ' . json_encode([stored($a->id), stored($c->id), stored($hidden->id)]);
    });

    check('--entry-id rebuilds just the one', function() use ($a, $b) {
        $db = Craft::$app->getDb();
        $db->createCommand()->update(RatingService::TABLE, ['count' => 50], ['elementId' => [$a->id, $b->id]])->execute();

        exec('php craft stars/ratings/rebuild --entry-id=' . $a->id . ' 2>&1', $out, $code);

        return $code === 0 && stored($a->id)['count'] === 1 && stored($b->id)['count'] === 50 ?: "exit $code: " . implode(' ', $out);
    });

    check('rebuildAll() removes a row whose entry has no approved reviews left', function() use ($ratings, $b) {
        Craft::$app->getDb()->createCommand()->upsert(RatingService::TABLE, ['elementId' => $b->id, 'average' => 1, 'count' => 1])->execute();
        $ratings->rebuildAll();

        return stored($b->id) === ['average' => 3.0, 'count' => 2] ?: json_encode(stored($b->id));
    });

    // ----------------------------------------------------------------------------------- field

    section('Stars Rating field');

    $find = fn() => Entry::find()->sectionId($section->id)->status(null);

    check('the field value is the stored summary', function() use ($find, $handle, $own) {
        $entry = (clone $find())->id($own->id)->one();
        $value = $entry->getFieldValue($handle);

        return $value instanceof RatingSummary && $value->count === 2 && $value->average === 4.5 && (string)$value === '4.5'
            ?: json_encode($value?->toArray());
    });

    check('an entry with no reviews reads 0 from 0, and counts as empty', function() use ($handle, $makeEntry, $field, $elements) {
        $empty = $makeEntry('Foxtrot');
        $value = $empty->getFieldValue($handle);
        $isEmpty = $field->isValueEmpty($value, $empty);
        $elements->deleteElement($empty, true);

        return $value->count === 0 && $value->average === 0.0 && $isEmpty ?: json_encode($value->toArray());
    });

    check('it is read-only: a value set on the entry is ignored and nothing reaches the content column', function() use ($a, $handle, $elements) {
        $entry = Entry::find()->id($a->id)->status(null)->one();
        $uid = $entry->getFieldLayout()->getFieldByHandle($handle)->layoutElement->uid;
        $entry->setFieldValue($handle, 99);
        $elements->saveElement($entry);
        $content = json_decode((string)(new Query())->select('content')->from('{{%elements_sites}}')->where(['elementId' => $a->id])->scalar(), true) ?: [];
        $fresh = Entry::find()->id($a->id)->status(null)->one();

        return $fresh->getFieldValue($handle)->average === 5.0 && ($content[$uid] ?? null) === null
            ?: json_encode($content);
    });

    check('a draft reads its canonical entry’s summary', function() use ($own, $handle) {
        $draft = Craft::$app->getDrafts()->createDraft($own, 1);
        $value = $draft->getFieldValue($handle);
        Craft::$app->getElements()->deleteElement($draft, true);

        return $value->count === 2 ?: json_encode($value->toArray());
    });

    check('element query param: rating(">= 4")', function() use ($find, $handle, $a, $own, $hidden) {
        $ids = (clone $find())->$handle('>= 4')->ids();
        sort($ids);
        $expected = [$a->id, $own->id, $hidden->id];
        sort($expected);

        return $ids === $expected ?: json_encode($ids);
    });

    check('element query param: {count: ">= 2"} and {average, count} together', function() use ($find, $handle, $b, $own) {
        $byCount = (clone $find())->$handle(['count' => '>= 2'])->ids();
        sort($byCount);
        $both = (clone $find())->$handle(['average' => '>= 4', 'count' => '>= 2'])->ids();
        $expected = [$b->id, $own->id];
        sort($expected);

        return $byCount === $expected && $both === [$own->id] ?: json_encode([$byCount, $both]);
    });

    check('orderBy the field handle, then by its count', function() use ($find, $handle, $a, $b, $c, $own, $hidden) {
        $byAverage = (clone $find())->orderBy([$handle => SORT_DESC, 'elements.id' => SORT_ASC])->ids();
        $byCount = (clone $find())->orderBy(["$handle.count" => SORT_DESC, 'elements.id' => SORT_ASC])->ids();

        // a 5.0/1, hidden 5.0/1, own 4.5/2, b 3.0/2, c 3.0/1
        $expectedAverage = [$a->id, $hidden->id, $own->id, $b->id, $c->id];
        $expectedCount = [$b->id, $own->id, $a->id, $c->id, $hidden->id];

        return $byAverage === $expectedAverage && $byCount === $expectedCount ?: json_encode([$byAverage, $byCount]);
    });

    check('the element index sort option orders by the stored average', function() use ($find, $field, $handle, $c) {
        $entry = Entry::find()->id($c->id)->status(null)->one();
        $instance = $entry->getFieldLayout()->getFieldByHandle($handle);
        $option = $instance->getSortOption();
        $ids = (clone $find())->orderBy([$option['orderBy'] => SORT_ASC, 'elements.id' => SORT_DESC])->ids();

        return $ids[0] === $c->id ?: json_encode([$option, $ids]);
    });

    check('index preview: stars and a count, nothing for no reviews', function() use ($field, $own) {
        $html = $field->getPreviewHtml(new RatingSummary(['average' => 4.5, 'count' => 2, 'maxRating' => 5]), $own);
        $none = $field->getPreviewHtml(new RatingSummary(), $own);

        return str_contains($html, 'star filled') && str_contains($html, '>2<') && $none === '' ?: $html;
    });

    check('the edit page shows the summary read-only, with no input', function() use ($field, $own, $handle) {
        $entry = Entry::find()->id($own->id)->status(null)->one();
        $html = $field->getInputHtml($entry->getFieldValue($handle), $entry);

        return str_contains($html, '4.5 out of 5 from 2 approved reviews') && !str_contains($html, '<input') ?: $html;
    });

    check('reading one entry of a listing preloads the summaries of all of them', function() use ($find, $handle) {
        $service = new RatingService();
        Plugin::getInstance()->set('ratings', $service);
        $entries = (clone $find())->all();
        $entries[0]->getFieldValue($handle);
        $loaded = array_keys((fn() => $this->_rows)->call($service));
        $missing = array_diff(array_map(fn(Entry $e) => $e->id, $entries), $loaded);

        return count($entries) > 1 && $missing === [] ?: 'not preloaded: ' . json_encode(array_values($missing));
    });

    // -------------------------------------------------------------------------- condition rule

    section('Condition rule');

    $rule = function(string $operator, string $value, string $maxValue = '') use ($field, $section): array {
        $condition = Entry::createCondition();
        $condition->sourceKey = "section:{$section->uid}";
        /** @var StarsRatingConditionRule $rule */
        $rule = $condition->createConditionRule([
            'class' => StarsRatingConditionRule::class,
            'fieldUid' => $field->uid,
            'operator' => $operator,
            'value' => $value,
            'maxValue' => $maxValue,
        ]);
        $condition->addConditionRule($rule);

        return [$condition, $rule];
    };

    check('the field offers the Stars rule', fn() => $field->getElementConditionRuleType() === StarsRatingConditionRule::class);

    check('"rated 4 or more" narrows an entry query', function() use ($rule, $find, $a, $own, $hidden) {
        [$condition] = $rule('>=', '4');
        $query = $find();
        $condition->modifyQuery($query);
        $ids = $query->ids();
        sort($ids);
        $expected = [$a->id, $own->id, $hidden->id];
        sort($expected);

        return $ids === $expected ?: json_encode($ids);
    });

    check('"between 3 and 4" uses both bounds', function() use ($rule, $find, $b, $c) {
        [$condition] = $rule('between', '3', '4');
        $query = $find();
        $condition->modifyQuery($query);
        $ids = $query->ids();
        sort($ids);
        $expected = [$b->id, $c->id];
        sort($expected);

        return $ids === $expected ?: json_encode($ids);
    });

    check('…and matches a loaded entry the same way', function() use ($rule, $own, $b) {
        [$condition] = $rule('>=', '4');
        $ownEntry = Entry::find()->id($own->id)->status(null)->one();
        $bEntry = Entry::find()->id($b->id)->status(null)->one();

        return $condition->matchElement($ownEntry) && !$condition->matchElement($bEntry) ?: 'match mismatch';
    });

    // -------------------------------------------------------------------------------- GraphQL

    section('GraphQL');

    $allSections = array_map(fn($s) => "sections.{$s->uid}:read", Craft::$app->getEntries()->getAllSections());
    // One process per query: see _gql.php for why.
    $run = function(array $scope, string $query): array {
        $out = shell_exec(sprintf(
            'php %s %s %s 2>&1',
            escapeshellarg(__DIR__ . '/_gql.php'),
            escapeshellarg(base64_encode(json_encode($scope))),
            escapeshellarg(base64_encode($query)),
        ));

        return json_decode((string)$out, true) ?? ['raw' => $out];
    };
    $fullScope = array_merge($allSections, ['stars.reviews:read', 'stars.comments:read']);

    check('starsReviews returns approved reviews of a live entry only', function() use ($run, $fullScope, $a, $suffix) {
        $result = $run($fullScope, "{ starsReviews(entryId: [{$a->id}]) { id rating reviewerName pros cons entryId } }");
        $names = array_column($result['data']['starsReviews'] ?? [], 'reviewerName');

        return $names === ["Ann $suffix"] ?: json_encode($result);
    });

    check('…ordered by an enum, counted, and limited', function() use ($run, $fullScope, $b) {
        $result = $run($fullScope, "{ starsReviews(entryId: [{$b->id}], orderBy: LOWEST) { rating } one: starsReviews(entryId: [{$b->id}], limit: 1) { id } starsReviewCount(entryId: [{$b->id}]) }");
        $ratings = array_column($result['data']['starsReviews'] ?? [], 'rating');

        return $ratings === [2, 4] && count($result['data']['one']) === 1 && $result['data']['starsReviewCount'] === 2 ?: json_encode($result);
    });

    check('the reviewer’s email, IP and user agent are not in the schema', function() use ($run, $fullScope) {
        // Craft answers an unknown field with an empty object rather than an error; what matters is
        // that nothing comes back.
        $result = $run($fullScope, '{ starsReviews { reviewerEmail ipAddress userAgent submissionUrl } }');
        $json = json_encode($result);

        return !str_contains($json, '@example.com') && !preg_match('/reviewerEmail|ipAddress|userAgent|submissionUrl"\s*:/', json_encode($result['data'] ?? [])) ?: $json;
    });

    check('an approved review on a disabled entry is not returned', function() use ($run, $fullScope, $hidden) {
        $result = $run($fullScope, "{ starsReviews(entryId: [{$hidden->id}]) { id } }");

        return ($result['data']['starsReviews'] ?? null) === [] ?: json_encode($result);
    });

    check('without stars.reviews:read the query does not exist', function() use ($run, $allSections) {
        $result = $run($allSections, '{ starsReviews { id } starsReviewCount }');

        return !isset($result['data']['starsReviews']) && !isset($result['data']['starsReviewCount']) ?: json_encode($result);
    });

    check('with stars.reviews:read but not the entry’s section, nothing comes back', function() use ($run, $section, $a) {
        $others = array_values(array_filter(
            array_map(fn($s) => $s->id === $section->id ? null : "sections.{$s->uid}:read", Craft::$app->getEntries()->getAllSections()),
        ));
        $result = $run(array_merge($others, ['stars.reviews:read']), "{ starsReviews(entryId: [{$a->id}]) { id } starsReviewCount(entryId: [{$a->id}]) }");

        return ($result['data']['starsReviews'] ?? null) === [] && $result['data']['starsReviewCount'] === 0 ?: json_encode($result);
    });

    $c1 = new Comment(['entryId' => $a->id, 'authorName' => "Ivy $suffix", 'authorEmail' => "stars-$suffix@example.com", 'body' => 'Approved', 'commentStatus' => 'approved']);
    $elements->saveElement($c1);
    $c2 = new Comment(['entryId' => $a->id, 'parentId' => $c1->id, 'authorName' => "Jo $suffix", 'body' => 'Reply', 'commentStatus' => 'approved']);
    $elements->saveElement($c2);
    $c3 = new Comment(['entryId' => $a->id, 'authorName' => "Kit $suffix", 'body' => 'Pending', 'commentStatus' => 'pending']);
    $elements->saveElement($c3);

    check('starsComments: approved only, with parentId and topLevel', function() use ($run, $fullScope, $a, $c1) {
        $result = $run($fullScope, "{ all: starsComments(entryId: [{$a->id}]) { body parentId } top: starsComments(entryId: [{$a->id}], topLevel: true) { body } starsCommentCount(entryId: [{$a->id}]) }");
        $all = $result['data']['all'] ?? [];

        return array_column($all, 'body') === ['Approved', 'Reply'] && $all[1]['parentId'] === $c1->id
            && array_column($result['data']['top'], 'body') === ['Approved'] && $result['data']['starsCommentCount'] === 2
            ?: json_encode($result);
    });

    check('comments need stars.comments:read, separately from reviews', function() use ($run, $allSections) {
        $result = $run(array_merge($allSections, ['stars.reviews:read']), '{ starsComments { id } starsReviewCount }');

        return !isset($result['data']['starsComments']) && isset($result['data']['starsReviewCount']) ?: json_encode($result);
    });

    check('entries carry starsRating and the field’s own summary', function() use ($run, $fullScope, $own, $handle) {
        $result = $run($fullScope, "{ entries(id: [{$own->id}]) { ... on starsType{$GLOBALS['suffix']}_Entry { starsRating { average count maxRating } $handle { average count } } } }");
        $entry = $result['data']['entries'][0] ?? [];

        return ($entry['starsRating'] ?? null) === ['average' => 4.5, 'count' => 2, 'maxRating' => 5] && ($entry[$handle] ?? null) === ['average' => 4.5, 'count' => 2]
            ?: json_encode($result);
    });

    check('…but not for a schema without stars.reviews:read', function() use ($run, $allSections, $own) {
        $result = $run($allSections, "{ entries(id: [{$own->id}]) { id starsRating { average } } }");
        $entry = $result['data']['entries'][0] ?? null;

        return is_array($entry) && isset($entry['id']) && !array_key_exists('starsRating', $entry) ?: json_encode($result);
    });

    // -------------------------------------------------------------------------------- JSON-LD

    section('JSON-LD');

    $schema = $plugin->schema;

    check('Product: stored aggregate, nameless review counted but not listed', function() use ($schema, $own) {
        $data = $schema->getSchema($own);

        return $data['@type'] === 'Product' && $data['aggregateRating']['ratingValue'] === 4.5 && $data['aggregateRating']['reviewCount'] === 2
            && count($data['review']) === 1 && $data['review'][0]['author']['name'] !== ''
            ?: json_encode($data);
    });

    check('the review limit setting caps the list, 0 gives the aggregate alone', function() use ($schema, $settings, $b) {
        $settings->schemaReviewLimit = 1;
        $one = $schema->getSchema($b);
        $settings->schemaReviewLimit = 0;
        $none = $schema->getSchema($b);
        $settings->schemaReviewLimit = 10;

        return count($one['review']) === 1 && !isset($none['review']) && isset($none['aggregateRating']) ?: json_encode([$one, $none]);
    });

    check('LocalBusiness (self-serving) prints nothing unless the reviews are about other businesses', function() use ($schema, $settings, $own) {
        $settings->schemaItemType = 'LocalBusiness';
        $selfServing = $schema->getSchema($own);
        $settings->schemaThirdPartyReviews = true;
        $directory = $schema->getSchema($own);
        $settings->schemaThirdPartyReviews = false;
        $settings->schemaItemType = 'Product';

        return $selfServing === null && ($directory['@type'] ?? null) === 'LocalBusiness' ?: json_encode([$selfServing, $directory]);
    });

    check('the type can be overridden per call', fn() => $schema->getSchema($own, ['type' => 'Course'])['@type'] === 'Course');

    check('the defineSchema event can change or drop the object', function() use ($schema, $own) {
        $handler = function(DefineSchemaEvent $event) {
            $event->schema = $event->entry->title === 'Own Delta' ? null : $event->schema;
        };
        Event::on(SchemaService::class, SchemaService::EVENT_DEFINE_SCHEMA, $handler);
        $dropped = $schema->getSchema($own);
        Event::off(SchemaService::class, SchemaService::EVENT_DEFINE_SCHEMA, $handler);

        return $dropped === null && $schema->getSchema($own) !== null ?: 'event ignored';
    });

    check('an entry without approved reviews prints nothing', function() use ($schema, $makeEntry, $elements) {
        $empty = $makeEntry('Golf');
        $html = $schema->generateJsonLd($empty);
        $elements->deleteElement($empty, true);

        return $html === '' ?: $html;
    });

    check('review text cannot close the script element', function() use ($c, $schema) {
        review($c->id, 4, 'approved', 'Xss', ['reviewText' => '</script><script>alert(1)</script>']);
        $html = $schema->generateJsonLd(Entry::find()->id($c->id)->one());

        return substr_count(strtolower($html), '</script>') === 1 && str_contains($html, '</script') ?: $html;
    });

    // HTTP: the page's own entry gets the JSON-LD injected before </head>, once.
    $configPath = Craft::getAlias('@config') . '/stars.php';

    if (file_exists($configPath)) {
        echo "  – skipped the HTTP injection checks: config/stars.php already exists\n";
    } else {
        $http = new Client(['base_uri' => 'http://127.0.0.1/', 'http_errors' => false, 'allow_redirects' => false]);
        $page = fn(Entry $entry) => (string)$http->get('index.php?p=' . rawurlencode($entry->uri))->getBody();
        // Count Stars' object, not script tags: SEOmatic (installed in the harness) prints its own.
        $tags = fn(string $html) => substr_count($html, '"AggregateRating"');

        check('injection off (the default): no JSON-LD on an entry page', function() use ($page, $tags, $a) {
            $html = $page($a);

            return str_contains($html, '<h1>Alpha</h1>') && $tags($html) === 0 ?: substr($html, 0, 300);
        });

        file_put_contents($configPath, "<?php\nreturn ['injectSchemaOrg' => true];\n");
        $fixtures['config'] = $configPath;

        check('injection on: the entry’s JSON-LD lands in the head', function() use ($page, $tags, $a) {
            $html = $page($a);
            $head = strstr($html, '</head>', true) ?: '';

            return $tags($head) === 1 && str_contains($head, '"Alpha"') ?: substr($html, 0, 600);
        });

        check('…and a template that prints it itself gets exactly one copy', function() use ($page, $tags, $own) {
            $html = $page($own);

            return $tags($html) === 1 ?: substr($html, 0, 600);
        });

        unlink($configPath);
        $fixtures['config'] = null;
    }

    // ------------------------------------------------------------------------------- settings

    section('Settings');

    check('the review limit must be 0–100', function() use ($settings) {
        $settings->schemaReviewLimit = -1;
        $invalid = !$settings->validate(['schemaReviewLimit']);
        $settings->schemaReviewLimit = 10;

        return $invalid && $settings->validate(['schemaReviewLimit']) ?: 'validated';
    });
} finally {
    section('Cleanup');

    if ($fixtures['config'] !== null && file_exists($fixtures['config'])) {
        unlink($fixtures['config']);
    }

    $plugin->setSettings($originalSettings);

    foreach (Review::find()->reviewerEmail("stars-$suffix@example.com")->status(null)->trashed(null)->all() as $element) {
        $elements->deleteElement($element, true);
    }

    foreach (Comment::find()->authorName("*$suffix")->status(null)->all() as $element) {
        $elements->deleteElement($element, true);
    }

    if ($fixtures['section'] !== null) {
        foreach (Entry::find()->sectionId($fixtures['section']->id)->status(null)->drafts(null)->all() as $entry) {
            $elements->deleteElement($entry, true);
        }
        Craft::$app->getEntries()->deleteSection($fixtures['section']);
    }

    if ($fixtures['entryType'] !== null) {
        Craft::$app->getEntries()->deleteEntryType($fixtures['entryType']);
    }

    if ($fixtures['field'] !== null) {
        Craft::$app->getFields()->deleteField($fixtures['field']);
    }

    if ($fixtures['template'] !== null && file_exists($fixtures['template'])) {
        unlink($fixtures['template']);
    }

    // Summaries for the entries that are gone go with them (FK cascade); the rest are untouched.
    echo "  fixtures removed\n";
}

echo "\n$passed passed, $failed failed\n";

exit($failed === 0 ? 0 : 1);
