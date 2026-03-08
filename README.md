# Stars — Review & Testimonial Management for Craft CMS 5

A structured review system for Craft CMS with star ratings, four-state moderation, pros/cons, admin responses, spam protection, and schema.org JSON-LD markup.

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
- **Four-state moderation** — pending, approved, rejected, spam
- **Pros & cons** — optional structured pro/con lists per review
- **Admin responses** — reply to reviews from the CP with timestamp
- **Spam protection** — honeypot, Google reCAPTCHA v3, IP rate limiting, submission time check
- **Schema.org** — JSON-LD output with `Review` + `AggregateRating` markup
- **Email notifications** — configurable recipients on new submissions
- **User permissions** — view, manage, moderate, respond, delete
- **Bulk actions** — approve, reject, mark as spam from the element index
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
            <option value="{{ i }}">{{ '★'|repeat(i) }}{{ '☆'|repeat(5 - i) }}</option>
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
            <span>{{ '★'|repeat(review.rating) }}{{ '☆'|repeat(5 - review.rating) }}</span>
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

    // Schema.org
    'enableSchemaOrg' => true,
    'schemaItemType' => 'Product',      // Product, LocalBusiness, Book, etc.

    // Features
    'enablePros' => true,
    'enableCons' => true,
    'enableAdminResponse' => true,
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

## Roadmap

- Verified purchase badge
- Review voting (helpful/not helpful)
- Media attachments (photos)
- Import/export tools
- GraphQL support
