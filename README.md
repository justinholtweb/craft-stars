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
- **Spam protection** — honeypot, per-IP rate limiting, submission time check
- **Login gating** — optionally require login (and auto-fill author details)
- **Schema.org** — JSON-LD output with `Review` + `AggregateRating` markup
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
{% set reviews = craft.reviews.forEntry(entry).all() %}
{% set avg = craft.reviews.averageRating(entry) %}
{% set count = craft.reviews.count(entry) %}

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
{% set dist = craft.reviews.distribution(entry) %}

{% for stars, count in dist|reverse %}
    <div>{{ stars }} stars: {{ count }}</div>
{% endfor %}
```

### Schema.org Markup

Place in your `<head>` to output valid JSON-LD for Google Rich Results:

```twig
{{ craft.reviews.schemaOrg(entry)|raw }}
```

### Twig API Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `craft.reviews.forEntry(entry)` | `ReviewQuery` | Approved reviews for an entry, newest first |
| `craft.reviews.averageRating(entry)` | `float` | Average rating (approved only) |
| `craft.reviews.count(entry)` | `int` | Count of approved reviews |
| `craft.reviews.distribution(entry)` | `array` | `{1: n, 2: n, ...}` rating histogram |
| `craft.reviews.schemaOrg(entry)` | `string` | JSON-LD `<script>` tag |

All methods accept an `Entry` object or an entry ID integer.

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
fed by `craft.comments.tree(entry)`:

```twig
{% import 'stars/_comments/thread' as commentThread %}
{{ commentThread.thread(craft.comments.tree(entry)) }}
```

Or build it yourself — each comment in the tree exposes its replies via
`.children`:

```twig
{% for comment in craft.comments.tree(entry) %}
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

### `craft.comments` API

| Method | Returns | Description |
|--------|---------|-------------|
| `craft.comments.tree(entry)` | `Comment[]` | Approved comments as a nested tree (replies on `.children`) |
| `craft.comments.forEntry(entry)` | `CommentQuery` | Approved comments for an entry, oldest first |
| `craft.comments.topLevel(entry)` | `CommentQuery` | Approved top-level comments (no replies) |
| `craft.comments.replies(comment)` | `array` | Approved replies to a comment |
| `craft.comments.count(entry)` | `int` | Count of approved comments |

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
    'minSubmissionTime' => 3,           // Seconds. 0 = disabled

    // Privacy — disable to avoid storing each piece of metadata
    'captureIpAddress' => true,         // Required for the per-IP rate limiter
    'captureUserAgent' => true,
    'captureReferrer' => true,

    // Schema.org
    'enableSchemaOrg' => true,
    'schemaItemType' => 'Product',      // Product, LocalBusiness, Book, etc.

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
- GraphQL support
