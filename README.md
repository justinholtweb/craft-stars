# Stars — Reviews & Comments for Craft CMS 5

A structured reviews **and** comments system for Craft CMS: star ratings, threaded comments, four-state moderation, pros/cons, admin responses, a submitter blocklist, pluggable captcha, spam protection, and schema.org JSON-LD markup.

## Requirements

- Craft CMS 5.0+
- PHP 8.2+

## Installation

```bash
composer require justinholtweb/craft-stars
php craft plugin/install stars
```

## Features

- **Star ratings** — configurable max (1-5 or 1-10)
- **Threaded comments** — a full comment system on entries with configurable reply depth
- **Four-state moderation** — pending, approved, rejected, spam (reviews and comments)
- **Pros & cons** — optional structured pro/con lists per review
- **Admin responses** — reply to reviews from the CP with timestamp
- **Blocklist** — block submitters by email, IP, or user id
- **Pluggable captcha** — reCAPTCHA v3, reCAPTCHA v2, hCaptcha, or Cloudflare Turnstile
- **Spam protection** — honeypot, per-entry and hourly per-address rate limits, submission time check
- **Login gating** — optionally require login (and auto-fill author details)
- **Schema.org** — JSON-LD output with `Review` + `AggregateRating` markup, in the template or added to entry pages automatically, following Google's review-snippet rules
- **Stars Rating field** — the stored average and count on the entry: sort and filter entries by rating, show it as an index column, use it in condition rules
- **GraphQL** — read-only queries for approved reviews and comments, plus `starsRating` on every entry
- **Email notifications** — new submissions to moderators, replies to comment authors
- **User permissions** — separate permission tiers for reviews, comments, and the blocklist
- **Bulk actions** — approve, reject, mark as spam, block author
- **Craft 5 native element editor** — sidebar fields, metadata, element chips

## Usage

### Frontend Form

```twig
<form method="post">
    {{ csrfInput() }}
    {{ actionInput('stars/reviews/save') }}
    {{ redirectInput('/thank-you') }}
    <input type="hidden" name="entryId" value="{{ entry.id }}">
    {# Honeypot (hidden from users, caught by bots) #}
    <input type="hidden" name="__stars_ts" value="{{ now|date('U') }}">
    <div style="position:absolute;left:-9999px" aria-hidden="true">
        <input type="text" name="starsHoneypot" tabindex="-1" autocomplete="off">
    </div>

    <label for="reviewerName">Your Name</label>
    <input type="text" id="reviewerName" name="reviewerName" required>

    <label for="reviewerEmail">Email</label>
    <input type="email" id="reviewerEmail" name="reviewerEmail">

    <label for="rating">Rating</label>
    <select id="rating" name="rating">
        {% for i in 1..5 %}
            <option value="{{ i }}">{{ '★★★★★'|slice(0, i) }}{{ '☆☆☆☆☆'|slice(0, 5 - i) }}</option>
        {% endfor %}
    </select>

    <label for="reviewText">Review</label>
    <textarea id="reviewText" name="reviewText"></textarea>

    <button type="submit">Submit Review</button>
</form>
```

#### With Pros & Cons

```twig
<label>Pros</label>
<input type="text" name="pros[]" placeholder="Pro 1">
<input type="text" name="pros[]" placeholder="Pro 2">

<label>Cons</label>
<input type="text" name="cons[]" placeholder="Con 1">
<input type="text" name="cons[]" placeholder="Con 2">
```

#### AJAX Submission

```js
const form = document.querySelector('#review-form');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await fetch('/', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: new FormData(form),
    });
    const data = await res.json();
    if (data.success) {
        // Review submitted
    } else {
        // Handle data.error or data.errors
    }
});
```

### Displaying Reviews

```twig
{% set reviews = craft.stars.reviews.forEntry(entry).all() %}
{% set avg = craft.stars.reviews.averageRating(entry) %}
{% set count = craft.stars.reviews.count(entry) %}

{% if count > 0 %}
    <p>{{ avg|number_format(1) }} out of 5 ({{ count }} {{ count == 1 ? 'review' : 'reviews' }})</p>

    {% for review in reviews %}
        <article class="review">
            <strong>{{ review.reviewerName }}</strong>
            <span>{{ '★★★★★'|slice(0, review.rating) }}{{ '☆☆☆☆☆'|slice(0, 5 - review.rating) }}</span>
            <time datetime="{{ review.dateCreated|date('Y-m-d') }}">{{ review.dateCreated|date('M j, Y') }}</time>

            {% if review.reviewText %}
                <p>{{ review.reviewText }}</p>
            {% endif %}

            {% set pros = review.prosArray %}
            {% if pros|length %}
                <ul class="pros">
                    {% for pro in pros %}<li>{{ pro }}</li>{% endfor %}
                </ul>
            {% endif %}

            {% set cons = review.consArray %}
            {% if cons|length %}
                <ul class="cons">
                    {% for con in cons %}<li>{{ con }}</li>{% endfor %}
                </ul>
            {% endif %}

            {% if review.adminResponse %}
                <blockquote>
                    <strong>Response:</strong> {{ review.adminResponse }}
                </blockquote>
            {% endif %}
        </article>
    {% endfor %}
{% endif %}
```

### Rating Distribution

```twig
{% set dist = craft.stars.reviews.distribution(entry) %}

{% for stars, count in dist|reverse %}
    <div>{{ stars }} stars: {{ count }}</div>
{% endfor %}
```

### Schema.org Markup

Place in your `<head>` to output valid JSON-LD for Google Rich Results:

```twig
{{ craft.stars.reviews.schemaOrg(entry) }}

{# Or with a different type for this section, and fewer reviews #}
{{ craft.stars.reviews.schemaOrg(entry, { type: 'Course', limit: 5 }) }}
```

It returns markup, so `|raw` isn't needed (templates that still have it keep working). Review text is
escaped so it can't close the `<script>` tag.

Or turn on **Add to Entry Pages Automatically** (`injectSchemaOrg`) and Stars adds the JSON-LD just
before `</head>` on every entry page that has approved reviews — the entry the URL matched, nothing
else. A page that already calls `schemaOrg()` for that entry gets no second copy.

What gets printed:

- The item (`Product` by default — the **Schema Item Type** setting) with its name and URL.
- `AggregateRating` from the stored summary (see [Stars Rating field](#stars-rating-field)).
- The newest approved reviews, up to **Schema Review Limit** (default 10; 0 = the aggregate only).
  Reviews without a reviewer name still count towards the aggregate but are left out of the list,
  because Google requires an author name.
- Nothing at all for an entry with no approved reviews.

**Self-serving reviews.** Google does not show stars for reviews a business collects about itself,
and treats marking them up as a guideline violation. So with `LocalBusiness`, `Organization` or one
of their subtypes (`Restaurant`, `Store`, `Hotel`…), Stars prints nothing unless **Reviews Are About
Other Businesses** (`schemaThirdPartyReviews`) is on — for a directory or guide reviewing businesses
it doesn't own.

To change the object per entry (a `brand`, an `sku`, a per-section type) or drop it, listen for
`SchemaService::EVENT_DEFINE_SCHEMA`:

```php
use justinholtweb\stars\events\DefineSchemaEvent;
use justinholtweb\stars\services\SchemaService;
use yii\base\Event;

Event::on(SchemaService::class, SchemaService::EVENT_DEFINE_SCHEMA, function(DefineSchemaEvent $event) {
    if ($event->schema !== null && $event->entry->section->handle === 'courses') {
        $event->schema['@type'] = 'Course';
        $event->schema['provider'] = ['@type' => 'Organization', 'name' => 'Acme Academy'];
    }
});
```

### Stars Rating field

`averageRating()` and `count()` work out the numbers from the reviews each time they're called, so
they can't sort or filter a list of entries. Stars also keeps a **stored summary** per entry — the
average and count of its approved reviews — updated whenever a review is approved, rejected, edited,
moved, deleted or restored. Writing it never re-saves the entry, so moderation doesn't create entry
revisions.

Add a **Stars Rating** field to an entry type's field layout to use it. The field is read-only; it
shows the summary on the entry's edit page, and can be an element index column and sort option.

```twig
{# Highest rated first #}
{% set products = craft.entries.section('products').orderBy('rating DESC').all() %}

{# Rated 4 or more #}
{% set best = craft.entries.section('products').rating('>= 4').all() %}

{# At least 3 reviews, averaging 4+ #}
{% set trusted = craft.entries.section('products').rating({ average: '>= 4', count: '>= 3' }).all() %}

{# Most reviewed #}
{% set popular = craft.entries.section('products').orderBy('rating.count DESC').all() %}

{{ entry.rating.average }} / {{ entry.rating.maxRating }} from {{ entry.rating.count }} reviews
```

(`rating` is whatever handle you gave the field.) Its value has `average`, `count`, `maxRating`,
`rounded`, `percent` (for star-fill widths) and `hasReviews`. Entries with no approved reviews read
an average and count of 0.

The field also adds a condition rule — "Stars Rating is greater than or equal to 4" — wherever
Craft builds entry conditions (custom sources, relation field conditions, Blitz refresh rules…).

Without a field, `craft.stars.reviews.summary(entry)` reads the same stored summary.

After importing reviews straight into the database, rebuild the summaries:

```bash
php craft stars/ratings/rebuild               # every entry
php craft stars/ratings/rebuild --entry-id=42 # one entry
```

### GraphQL

Stars adds two schema components under **Stars** in each GraphQL schema: **Query for approved
reviews** (`stars.reviews:read`) and **Query for approved comments** (`stars.comments:read`).

```graphql
{
  starsReviews(entryId: [42], orderBy: NEWEST, limit: 10) {
    id rating reviewText reviewerName pros cons adminResponse adminResponseDate dateCreated
  }
  starsReviewCount(entryId: [42], minRating: 4)
  starsComments(entryId: [42]) { id parentId authorName body dateCreated }
  starsCommentCount(entryId: [42])
  entries(section: "products") {
    title
    starsRating { average count maxRating }
  }
}
```

- Only **approved** reviews and comments are returned, and only for entries that are live and in a
  section the schema can read — the same entries the token could fetch itself.
- `starsReviews` takes `entryId`, `rating`, `minRating`, `maxRating`, `limit` (max 100), `offset`
  and `orderBy` (`NEWEST`, `OLDEST`, `HIGHEST`, `LOWEST`). `starsComments` takes `entryId`,
  `parentId`, `topLevel`, `limit`, `offset` and `orderBy` (`OLDEST`, `NEWEST`); build threads from
  `parentId`.
- Reviewer and commenter emails, IP addresses, user agents and referrers are never exposed.
- `starsRating` on entries (and a Stars Rating field's own value) needs `stars.reviews:read`.
- It is read-only. Submit reviews and comments through the `stars/reviews/save` and
  `stars/comments/save` actions, which run the spam checks.

### Twig API Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `craft.stars.reviews.forEntry(entry)` | `ReviewQuery` | Approved reviews for an entry, newest first |
| `craft.stars.reviews.averageRating(entry)` | `float` | Average rating (approved only) |
| `craft.stars.reviews.count(entry)` | `int` | Count of approved reviews |
| `craft.stars.reviews.distribution(entry)` | `array` | `{1: n, 2: n, ...}` rating histogram |
| `craft.stars.reviews.summary(entry)` | `RatingSummary` | Stored average + count (one row, no aggregate) |
| `craft.stars.reviews.schemaOrg(entry, options)` | `Markup` | JSON-LD `<script>` tag; options `type`, `limit` |

All methods accept an `Entry` object or an entry ID integer.

#### Deprecated top-level variables

Before 5.1.0 the plugin registered `craft.reviews` and `craft.comments`.
`craft.comments` is also used by [verbb/comments][verbb-comments], so with both
plugins installed one silently replaced the other. Everything now lives under
`craft.stars`.

The old names still work for existing templates, but only when no other plugin
has claimed them — if verbb/comments is installed, it keeps `craft.comments` and
Stars stays out of the way. Move to `craft.stars.reviews` /
`craft.stars.comments`; the aliases will be removed in 6.0.0.

[verbb-comments]: https://plugins.craftcms.com/comments

## Comments

Stars also provides a threaded comment system on entries, with the same
moderation, spam protection, and login-gating as reviews.

### Comment Form

```twig
<form method="post">
    {{ csrfInput() }}
    {{ actionInput('stars/comments/save') }}
    {{ redirectInput('') }}
    <input type="hidden" name="entryId" value="{{ entry.id }}">
    {# For a reply, include the parent comment's id: #}
    {# <input type="hidden" name="parentId" value="{{ parentComment.id }}"> #}
    <input type="hidden" name="__stars_ts" value="{{ now|date('U') }}">
    <div style="position:absolute;left:-9999px" aria-hidden="true">
        <input type="text" name="starsHoneypot" tabindex="-1" autocomplete="off">
    </div>

    {# Name/email are only used for guests; logged-in users are filled in automatically. #}
    <label for="authorName">Name</label>
    <input type="text" id="authorName" name="authorName">

    <label for="body">Comment</label>
    <textarea id="body" name="body" required></textarea>

    <button type="submit">Post Comment</button>
</form>
```

### Displaying Comments

The simplest way to render a full nested thread is the bundled recursive macro,
fed by `craft.stars.comments.tree(entry)`:

```twig
{% import 'stars/_comments/thread' as commentThread %}
{{ commentThread.thread(craft.stars.comments.tree(entry)) }}
```

Or build it yourself — each comment in the tree exposes its replies via
`.children`:

```twig
{% for comment in craft.stars.comments.tree(entry) %}
    <article class="comment">
        <strong>{{ comment.authorName }}</strong>
        <p>{{ comment.body }}</p>

        {% for reply in comment.children %}
            <article class="comment comment--reply">
                <strong>{{ reply.authorName }}</strong>
                <p>{{ reply.body }}</p>
            </article>
        {% endfor %}
    </article>
{% endfor %}
```

Reply nesting is capped by the **Max Comment Depth** setting; deeper replies are
automatically attached at the deepest allowed level.

### `craft.stars.comments` API

| Method | Returns | Description |
|--------|---------|-------------|
| `craft.stars.comments.tree(entry)` | `Comment[]` | Approved comments as a nested tree (replies on `.children`) |
| `craft.stars.comments.forEntry(entry)` | `CommentQuery` | Approved comments for an entry, oldest first |
| `craft.stars.comments.topLevel(entry)` | `CommentQuery` | Approved top-level comments (no replies) |
| `craft.stars.comments.replies(comment)` | `array` | Approved replies to a comment |
| `craft.stars.comments.count(entry)` | `int` | Count of approved comments |

## Configuration

All settings are available in the CP under **Stars > Settings**. You can also override them in `config/stars.php`:

```php
<?php

return [
    // Moderation
    'defaultStatus' => 'pending',       // 'pending' or 'approved'
    'requireLogin' => false,
    'allowAnonymous' => false,

    // Rating
    'maxRating' => 5,                   // 1-10

    // Notifications
    'enableNotifications' => true,
    'notificationEmails' => '',         // Comma-separated, blank = system email

    // Anti-Spam
    'enableHoneypot' => true,
    'enableRecaptcha' => false,
    'recaptchaSiteKey' => '$RECAPTCHA_SITE_KEY',
    'recaptchaSecretKey' => '$RECAPTCHA_SECRET_KEY',
    'rateLimitMinutes' => 1440,         // Per IP+entry. 0 = disabled
    'maxSubmissionsPerHour' => 10,      // Per address, across every entry. 0 = disabled
    'minSubmissionTime' => 3,           // Seconds. 0 = disabled

    // Privacy — disable to avoid storing each piece of metadata
    'captureIpAddress' => true,         // Required for the per-entry limit (the hourly one doesn't store IPs)
    'captureUserAgent' => true,
    'captureReferrer' => true,

    // Schema.org
    'enableSchemaOrg' => true,
    'schemaItemType' => 'Product',      // Product, LocalBusiness, Book, etc.
    'schemaThirdPartyReviews' => false, // Mark up LocalBusiness/Organization only when reviews are about other businesses
    'schemaReviewLimit' => 10,          // Individual reviews in the JSON-LD. 0 = aggregate only
    'injectSchemaOrg' => false,         // Add the JSON-LD to entry pages without a template change

    // Features
    'enablePros' => true,
    'enableCons' => true,
    'enableAdminResponse' => true,

    // Comments
    'enableComments' => true,
    'commentsRequireLogin' => false,
    'commentsAllowAnonymous' => false,
    'maxCommentDepth' => 2,             // 1 = no replies, 2 = one level, ...
];
```

## Permissions

| Permission | Description |
|------------|-------------|
| `stars:viewReviews` | View the Reviews section in the CP |
| `stars:manageReviews` | Create and edit reviews |
| `stars:moderateReviews` | Approve, reject, and mark as spam |
| `stars:respondToReviews` | Add admin responses |
| `stars:deleteReviews` | Delete reviews |

Admin users have all permissions by default.

## Events

The plugin uses standard Craft element events. You can listen for review saves, deletes, etc.:

```php
use craft\events\ModelEvent;
use justinholtweb\stars\elements\Review;
use yii\base\Event;

Event::on(Review::class, Review::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    /** @var Review $review */
    $review = $event->sender;
    // Your logic here
});
```

## Development

The plugin ships with a test suite built on Codeception and Craft's test
framework. [DDEV](https://ddev.com) provides PHP and a database (no local PHP
install required):

```bash
ddev start
ddev composer install
ddev exec vendor/bin/codecept build
ddev exec vendor/bin/codecept run unit
```

The suite boots a real Craft application, installs the plugin (running its
migration), and exercises the services against a live database.

## Roadmap

- Verified purchase badge
- Review voting (helpful/not helpful)
- Media attachments (photos)
- Import/export tools
- GraphQL mutations for submissions
